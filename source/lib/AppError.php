<?php
final class AppError extends RuntimeException
{
    public string $errorCode;
    public int $httpStatus;
    public array $fields;

    public function __construct(string $code, int $httpStatus = 400, ?string $message = null, array $fields = [])
    {
        $this->errorCode = $code;
        $this->httpStatus = $httpStatus;
        $this->fields = $fields;
        parent::__construct($message ?? $code, $httpStatus);
    }
}
