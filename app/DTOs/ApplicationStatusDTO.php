<?php

namespace App\DTOs;


final class ApplicationStatusDTO
{
  public function __construct(
    public int $processSelectionId,
    public string $processSelectionName,
    public string $candidateName,
    public string $status,
    public array $reasons,
  ) 
  {
  }

  public function toArray(): array
  {
    return [
      'processSelectionId' => $this->processSelectionId,
      'processSelectionName' => $this->processSelectionName,
      'candidateName'        => $this->candidateName,
      'status'               => $this->status,
      'reasons'              => $this->reasons,
    ];
  }
}
