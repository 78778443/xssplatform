<?php
/**
 * Notification facade backed by PHPMailer 6 and curl for Feishu bot webhooks.
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

    public static function validWebhook(string $url): bool
    {
        return preg_match('~^https://open\.feishu\.cn/open-apis/bot/v2/hook/[A-Za-z0-9-]+$~', $url) === 1;
    }

    public static function sendFeishu(string $message, string $webhook): bool
    {
        if ($webhook === '') {
            return false;
        }
        $feishu = Config::get('feishu', []);
        // Tests use a local mock endpoint; production always uses the saved webhook.
        $endpoint = (string) ($feishu['endpoint'] ?? '');
        if ($endpoint === '') {
            if (!self::validWebhook($webhook)) {
                return false;
            }
            $endpoint = $webhook;
        }
        $keyword = (string) ($feishu['keyword'] ?? '');
        $text = ($keyword !== '' ? $keyword . '：' : '') . $message;
        $data = [
            'msg_type' => 'text',
            'content' => ['text' => $text],
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json;charset=utf-8']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($result === false) {
            error_log('Feishu notification failed: ' . curl_error($ch));
        }
        curl_close($ch);
        if ($result === false || $status < 200 || $status >= 300) {
            error_log('Feishu notification rejected: HTTP ' . $status . ' ' . (string) $result);
            return false;
        }
        $decoded = json_decode((string) $result, true);
        if (is_array($decoded)) {
            $code = $decoded['code'] ?? $decoded['StatusCode'] ?? 0;
            if ((int) $code !== 0) {
                error_log('Feishu notification rejected: ' . (string) $result);
                return false;
            }
        }
        return true;
    }
}
