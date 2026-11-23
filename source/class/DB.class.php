<?php
/**
 * DB.class.php database operator
 *
 * All SQL goes through mysqli prepared statements. Callers pass "?" placeholders
 * and parameter arrays; values are never concatenated into SQL.
 *
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if (!defined('IN_OLDCMS')) {
    die('Access Denied');
}

class BlueDB
{
    public static function DB($databaseType = 'mysql')
    {
        switch (strtolower($databaseType)) {
        case 'mysql':
            return new DB_Mysql;
        default:
            return false;
        }
    }
}

interface IDataBase
{
    public function Connect(
        $dbHost = 'localhost',
        $dbUser = 'root',
        $dbPwd = '',
        $dbName = '',
        $dbCharset = 'utf8',
        $tbPrefix = ''
    );

    public function Dataset($sql, array $params = array());

    public function FirstRow($sql, array $params = array());

    public function FirstColumn($sql, array $params = array());

    public function FirstValue($sql, array $params = array());

    public function Execute($sql, array $params = array());
}

/**
 * Mysql
 */
class DB_Mysql implements IDataBase
{
    public $linkId = null;
    public $queryId = null;
    public $rows = array();
    public $rowsNum = 0;
    public $tbPrefix = '';
    private $host, $username, $password, $database, $charset, $port = 3306, $error = '';

    public function __destruct()
    {
        $this->Disconnect();
    }

    /* connect to database */
    public function Connect(
        $dbHost = 'localhost',
        $dbUser = 'root',
        $dbPwd = '',
        $dbName = '',
        $dbCharset = 'utf8',
        $tbPrefix = ''
    ) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $this->error = '';
        $this->host = (string) $dbHost;
        $this->port = 3306;
        if (strpos($this->host, ':') !== false) {
            [$host, $port] = explode(':', $this->host, 2);
            $this->host = $host;
            $this->port = (int) $port;
        }
        $this->username = (string) $dbUser;
        $this->password = (string) $dbPwd;
        $this->database = (string) $dbName;
        $this->charset = (string) $dbCharset;
        $this->tbPrefix = (string) $tbPrefix;

        try {
            $this->linkId = new mysqli($this->host, $this->username, $this->password, $this->database, $this->port);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->linkId = null;
            return false;
        }
        if (!empty($this->linkId->connect_errno)) {
            $this->error = $this->linkId->connect_error;
            $this->linkId = null;
            return false;
        }
        if (!mysqli_set_charset($this->linkId, $this->charset)) {
            $this->error = mysqli_error($this->linkId);
            return false;
        }
        return true;
    }

    /* disconnect from database */
    private function Disconnect()
    {
        if (is_object($this->queryId) && $this->queryId instanceof mysqli_result) {
            mysqli_free_result($this->queryId);
        }
        $this->queryId = null;
        if (is_object($this->linkId) && $this->linkId instanceof mysqli) {
            mysqli_close($this->linkId);
        }
        $this->linkId = null;
    }

    private function bindTypes(array $params): string
    {
        $types = '';
        foreach ($params as $value) {
            $types .= is_int($value) ? 'i' : 's';
        }
        return $types;
    }

    private function prepare(string $sql, array $params)
    {
        if (!is_object($this->linkId) || !($this->linkId instanceof mysqli)) {
            throw new RuntimeException('Database is not connected');
        }
        if (substr_count($sql, '?') !== count($params)) {
            throw new InvalidArgumentException(
                'Placeholder count (' . substr_count($sql, '?') . ') does not match parameter count (' . count($params) . ')'
            );
        }
        $stmt = mysqli_prepare($this->linkId, $sql);
        if ($stmt === false) {
            $this->error = mysqli_error($this->linkId);
            throw new RuntimeException('Failed to prepare statement: ' . $this->error);
        }
        if ($params !== array()) {
            $types = $this->bindTypes($params);
            $bind = array($types);
            foreach ($params as $key => $value) {
                $bind[] = &$params[$key];
            }
            $ok = call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt), $bind));
            if ($ok === false) {
                $this->error = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Failed to bind parameters: ' . $this->error);
            }
        }
        if (!mysqli_stmt_execute($stmt)) {
            $this->error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new RuntimeException('Failed to execute statement: ' . $this->error);
        }
        return $stmt;
    }

    /* execute any query; returns a result for SELECT, true for writes */
    public function Query($sql, array $params = array())
    {
        $stmt = $this->prepare((string) $sql, $params);
        $result = mysqli_stmt_get_result($stmt);
        if ($result === false) {
            mysqli_stmt_close($stmt);
            return true;
        }
        $this->queryId = $result;
        return $result;
    }

    /* auto execute type=>insert/update (legacy compatibility; Task 10 replaces callers) */
    public function AutoExecute($table, $array = array(), $type = 'INSERT', $where = '')
    {
        if (empty($array) || empty($table)) {
            return false;
        }
        switch (strtoupper($type)) {
        case 'INSERT':
            return $this->Insert($table, $array) > 0;
        case 'UPDATE':
            return $this->Update($table, $array, $where);
        default:
            return false;
        }
    }

    /* return dataset of query */
    public function Dataset($sql, array $params = array())
    {
        $this->rows = array();
        $result = $this->Query((string) $sql, $params);
        if (!($result instanceof mysqli_result)) {
            $this->rowsNum = 0;
            return $this->rows;
        }
        while ($row = mysqli_fetch_assoc($result)) {
            $this->rows[] = $row;
        }
        $this->rowsNum = count($this->rows);
        return $this->rows;
    }

    /* return first row */
    public function FirstRow($sql, array $params = array())
    {
        $result = $this->Query((string) $sql, $params);
        if (!($result instanceof mysqli_result)) {
            $this->rowsNum = 0;
            return false;
        }
        $row = mysqli_fetch_assoc($result);
        if (!empty($row)) {
            $this->rowsNum = 1;
            return $row;
        }
        $this->rowsNum = 0;
        return false;
    }

    /* return first column (array) */
    public function FirstColumn($sql, array $params = array())
    {
        $columns = array();
        $result = $this->Query((string) $sql, $params);
        if ($result instanceof mysqli_result) {
            while ($row = mysqli_fetch_row($result)) {
                $columns[] = $row[0];
            }
        }
        $this->rowsNum = count($columns);
        return $columns;
    }

    /* return first value */
    public function FirstValue($sql, array $params = array())
    {
        $result = $this->Query((string) $sql, $params);
        if (!($result instanceof mysqli_result)) {
            $this->rowsNum = 0;
            return false;
        }
        $row = mysqli_fetch_row($result);
        if (!empty($row)) {
            $this->rowsNum = 1;
            return $row[0];
        }
        $this->rowsNum = 0;
        return false;
    }

    /* execute without result */
    public function Execute($sql, array $params = array())
    {
        $this->Query((string) $sql, $params);
        return true;
    }

    /* insert one row, return last id */
    public function Insert($table, array $data)
    {
        if ($data === array()) {
            throw new InvalidArgumentException('Insert data must not be empty');
        }
        $columns = array_map(function ($column) {
            return '`' . trim((string) $column, '`') . '`';
        }, array_keys($data));
        $placeholders = array_fill(0, count($columns), '?');
        $sql = 'INSERT INTO `' . trim((string) $table, '`') . '`(' . implode(',', $columns) . ') VALUES(' . implode(',', $placeholders) . ')';
        $this->Execute($sql, array_values($data));
        return $this->LastId();
    }

    /* update rows; $where is SQL with placeholders */
    public function Update($table, array $data, $where, array $whereParams = array())
    {
        if ($data === array()) {
            throw new InvalidArgumentException('Update data must not be empty');
        }
        $sets = array();
        foreach (array_keys($data) as $column) {
            $sets[] = '`' . trim((string) $column, '`') . '`=?';
        }
        $sql = 'UPDATE `' . trim((string) $table, '`') . '` SET ' . implode(',', $sets);
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $params = array_merge(array_values($data), $whereParams);
        $this->Execute($sql, $params);
        return true;
    }

    /* last id */
    public function LastId()
    {
        return $this->linkId instanceof mysqli ? (int) mysqli_insert_id($this->linkId) : 0;
    }

    /* last error message */
    public function Error()
    {
        if ($this->error !== '') {
            return $this->error;
        }
        return $this->linkId instanceof mysqli ? mysqli_error($this->linkId) : '';
    }
}
