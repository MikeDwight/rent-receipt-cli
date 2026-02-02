<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\DTO;

final class GenerateReceiptsResult
{
    /** @var array<int, array<string,mixed>> */
    public array $created = [];

    /** @var array<int, array<string,mixed>> */
    public array $skipped = [];
}
