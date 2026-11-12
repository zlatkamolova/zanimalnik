<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Setting> */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function getValue(string $key, string $default = ''): string
    {
        return $this->find($key)?->getValue() ?? $default;
    }

    /** Returns the setting, creating (but not flushing) it when missing. */
    public function getOrCreate(string $key): Setting
    {
        $setting = $this->find($key);
        if (!$setting) {
            $setting = new Setting($key);
            $this->getEntityManager()->persist($setting);
        }

        return $setting;
    }
}
