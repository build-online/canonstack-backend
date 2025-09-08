<?php

namespace App\Http\Serializers;

use League\Fractal\Serializer\ArraySerializer;

class ApiSerializer extends ArraySerializer
{
  /**
   *
   * @param string|null $resourceKey
   * @param array $data
   * @return array
   */
  public function collection(?string $resourceKey, array $data): array
  {
    if ($resourceKey) {
      return [$resourceKey => $data];
    }
    return $data;
  }

  /**
   *
   * @param string|null $resourceKey
   * @param array $data
   * @return array
   */
  public function item(?string $resourceKey, array $data): array
  {
    if ($resourceKey) {
      return [$resourceKey => $data];
    }
    return $data;
  }
}
