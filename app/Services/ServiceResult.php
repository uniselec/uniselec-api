<?php
// Padrão ServiceResult

namespace App\Services;

class ServiceResult
{
  protected string $status;
  protected ?string $message;
  protected $data;
  protected ?string $code;

  private function __construct(string $status, $data = null, ?string $message = null, ?string $code = null)
  {
    $this->status = $status;
    $this->data = $data;
    $this->message = $message;
    $this->code = $code;
  }

  public static function success($data = null, ?string $message = null): self
  {
    return new self('success', $data, $message);
  }

  public static function failure($data = null, ?string $message = null): self
  {
    return new self('failure', $data, $message);
  }

  public static function withStatus(string $status, $data = null, ?string $message = null, string|int|null $code = null): self
  {
    return new self($status, $data, $message, $code);
  }

  public function isSuccess(): bool
  {
    return $this->status === 'success';
  }

  public function isFailure(): bool
  {
    return $this->status === 'failure';
  }

  public function getStatus(): string
  {
    return $this->status;
  }

  public function getMessage(): ?string
  {
    return $this->message;
  }

  public function getData()
  {
    return $this->data;
  }

  public function getCode()
  {
    return $this->code;
  }
}
