<?php
final class ConfigWriter
{
    public static function write(string $path, array $config): void
    {
        $content = "<?php\n\$config = " . var_export($config, true) . ";\n";
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $content) === false) {
            throw new RuntimeException('Unable to write temporary config file');
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to move config file into place');
        }
        @chmod($path, 0640);
    }
}
