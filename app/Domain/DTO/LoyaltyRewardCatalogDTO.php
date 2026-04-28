<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

final readonly class LoyaltyRewardCatalogDTO implements DomainTransferObject
{
    public function __construct(
        public int $id,
        public string $reward_type,
        public string $name_es,
        public string $name_en,
        public int $stamps_required,
        public string $tier_required,
        public int $validity_days,
        public bool $is_active,
        public int $display_order,
        public ?string $icon,
    ) {}

    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'reward_type' => $this->reward_type,
            'name_es' => $this->name_es,
            'name_en' => $this->name_en,
            'stamps_required' => $this->stamps_required,
            'tier_required' => $this->tier_required,
            'validity_days' => $this->validity_days,
            'is_active' => $this->is_active,
            'display_order' => $this->display_order,
            'icon' => $this->icon,
        ];
    }
}
