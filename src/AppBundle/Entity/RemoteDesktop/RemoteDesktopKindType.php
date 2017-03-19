<?php

namespace AppBundle\Entity\RemoteDesktop;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class RemoteDesktopKindType extends Type
{
    public function getName()
    {
        return 'RemoteDesktopKindType';
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'SMALLINT';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ((int)$value === RemoteDesktopKind::GAMING) {
            return new RemoteDesktopGamingKind();
        } else {
            throw new \Exception('Could not convert the RemoteDesktopKind value ' . $value . ' to a known RemoteDesktopKind object');
        }
    }

    public function convertToDatabaseValue($valueObject, AbstractPlatform $platform)
    {
        if ($valueObject instanceof RemoteDesktopGamingKind) {
            $value = RemoteDesktopKind::GAMING;
        } else {
            throw new \Exception('Could not convert the RemoteDesktopKind object of class ' . get_class($valueObject) . ' to a known RemoteDesktopKind value');
        }

        return $value;
    }
}
