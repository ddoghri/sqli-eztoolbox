<?php

namespace SQLI\EzToolboxBundle\Services;

use eZ\Publish\Core\MVC\Symfony\SiteAccess;

trait SiteAccessUtilsTrait
{
    /** @var SiteAccess */
    protected $siteAccess;
    /** @var array */
    protected $siteaccessAdminGroup;

    /**
     * autowiring
     * @required
     * @param SiteAccess $siteAccess
     * @param array      $siteaccessAdminGroup
     */
    public function setSiteAccessSettings(SiteAccess $siteAccess, $siteaccessAdminGroup)
    {
        $this->siteAccess           = $siteAccess;
        $this->siteaccessAdminGroup = $siteaccessAdminGroup;
    }

    /**
     * Check if specified (or current if null) siteaccess name is in admin group
     *
     * @param string|null $siteaccess
     * @return bool
     */
    public function isAdminSiteAccess(?string $siteaccess = null): bool
    {
        if (is_null($siteaccess)) {
            $siteaccess = $this->getSiteAccessName();
        }

        return in_array($siteaccess, $this->siteaccessAdminGroup['admin_group']);
    }

    /**
     * @return string
     */
    public function getSiteAccessName(): string
    {
        return $this->siteAccess->name;
    }
}