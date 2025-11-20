<?php

namespace App\Enums;

enum InconsistencyType: string
{
  case NAME = 'Inconsistência no Nome';
  case BIRTHDATE = 'Inconsistência na Data de Nascimento';
  case CPF = 'Inconsistência no CPF';

  /**
   * Retorna os valores como array
   */
  public static function values(): array
  {
    return array_map(fn (self $case) => $case->value, self::cases());
  }
}
