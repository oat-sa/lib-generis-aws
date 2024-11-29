<?php

namespace oat\awsTools;

use League\Flysystem\AwsS3V3\VisibilityConverter;
use League\Flysystem\Visibility;

class BucketOwnerVisibilityConverter implements VisibilityConverter
{
    private const PUBLIC_GRANTEE_URI = 'http://acs.amazonaws.com/groups/global/AllUsers';
    private const PUBLIC_GRANTS_PERMISSION = 'READ';
    private const BUCKET_OWNER_ACL = 'bucket-owner-full-control';

    public function __construct(private string $defaultForDirectories = Visibility::PUBLIC)
    {
    }

    public function visibilityToAcl(string $visibility): string
    {
        return self::BUCKET_OWNER_ACL;
    }

    public function aclToVisibility(array $grants): string
    {
        foreach ($grants as $grant) {
            $granteeUri = $grant['Grantee']['URI'] ?? null;
            $permission = $grant['Permission'] ?? null;

            if ($granteeUri === self::PUBLIC_GRANTEE_URI && $permission === self::PUBLIC_GRANTS_PERMISSION) {
                return Visibility::PUBLIC;
            }
        }

        return Visibility::PRIVATE;
    }

    public function defaultForDirectories(): string
    {
        return $this->defaultForDirectories;
    }
}