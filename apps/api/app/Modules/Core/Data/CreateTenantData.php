<?php

namespace App\Modules\Core\Data;

final readonly class CreateTenantData
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $ownerName,
        public ?string $ownerEmail,
        public ?string $ownerPhoneE164,
        public string $ownerPassword,
        public string $firstBranchName = 'شعبه مرکزی',
        public ?string $subdomainBase = null,
    ) {}
}
