<?php
/**
 * Notification facade backed by PHPMailer 6 and curl for DingTalk.
 */
final class Notify
{
    public static function sendMail(string $to, string $subject, string $body): bool
    {
        $email = Config::get('email', []);
        if ($to === '' || empty($email['host'])) {
            return false;
        }
        require_once ROOT_PATH . '/libs/phpmailer/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = (string) $email['host'];
            $mail->Port = (int) ($email['port'] ?? 465);
            $auth = $email['auth'] ?? true;
            $mail->SMTPAuth = (bool) $auth;
            if ($auth) {
                $mail->Username = (string) ($email['username'] ?? '');
                $mail->Password = (string) ($email['password'] ?? '');
            }
            $secure = (string) ($email['secure'] ?? '');
            $mail->SMTPSecure = $secure !== '' ? $secure : '';
            $from = (string) ($email['username'] ?? '');
            if ($from === '') {
                $from = 'noreply@localhost';
            }
            $mail->setFrom($from, (string) ($email['fromName'] ?? 'Mailer'));
            $mail->addAddress($to, 'Joe');
            $mail->addReplyTo($from, 'info');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($subject);
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('SendMail failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function sendDing(string $message, string $key): bool
    {
        if ($key === '') {
            return false;
        }
        $ding = Config::get('dingding', []);
        $endpoint = (string) ($ding['endpoint'] ?? '');
        if ($endpoint === '') {
            $endpoint = 'https://oapi.dingtalk.com/robot/send?access_token=' . rawurlencode($key);
        }
        $data = [
            'msgtype' => 'text',
            'text' => ['content' => (string) ($ding['keyword'] ?? '') . '：' . $message],
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json;charset=utf-8']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $ok = $result !== false;
        if (!$ok) {
            error_log('DingTalk notification failed: ' . curl_error($ch));
        }
        curl_close($ch);
        return $ok;
    }
}
