<?php

namespace SQLI\EzToolboxBundle\Services\Listener;

use eZ\Publish\API\Repository\Events\Content\CopyContentEvent;
use eZ\Publish\API\Repository\Events\Content\DeleteContentEvent;
use eZ\Publish\API\Repository\Events\Content\HideContentEvent;
use eZ\Publish\API\Repository\Events\Content\PublishVersionEvent;
use eZ\Publish\API\Repository\Events\Content\RevealContentEvent;
use eZ\Publish\API\Repository\Events\Location\CreateLocationEvent;
use eZ\Publish\API\Repository\Events\Location\DeleteLocationEvent;
use eZ\Publish\API\Repository\Events\Location\HideLocationEvent;
use eZ\Publish\API\Repository\Events\Location\UnhideLocationEvent;
use eZ\Publish\API\Repository\Events\Trash\TrashEvent;
use eZ\Publish\API\Repository\Events\User\CreateUserEvent;
use eZ\Publish\API\Repository\Events\User\DeleteUserEvent;
use eZ\Publish\API\Repository\Events\User\UpdateUserEvent;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\Core\MVC\Symfony\Security\UserInterface;
use eZ\Publish\Core\MVC\Symfony\SiteAccess;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Netgen\TagsBundle\API\Repository\TagsService;
use SQLI\EzToolboxBundle\Services\Formatter\SqliSimpleLogFormatter;
use SQLI\EzToolboxBundle\Services\SiteAccessUtilsTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


class BackOfficeActionsLoggerListener implements EventSubscriberInterface
{
    use SiteAccessUtilsTrait;

    /** @var TokenStorageInterface */
    private $tokenStorage;
    /** @var Repository */
    private $repository;
    /** @var string */
    private $logDir;
    /** @var Request */
    private $request;
    /** @var TagsService */
    private $tagsService;
    /** @var bool */
    private $adminLoggerEnabled;

    /**
     * BackOfficeActionsLoggerListener constructor.
     * @param TokenStorageInterface $tokenStorage
     * @param Repository $repository
     * @param string $logDir
     * @param RequestStack $requestStack
     * @param TagsService $tagsService
     * @param $adminLoggerEnabled
     * @param SiteAccess $siteAccess
     */
    public function __construct(
        TokenStorageInterface $tokenStorage,
        Repository $repository,
        string $logDir,
        RequestStack $requestStack,
        TagsService $tagsService,
        $adminLoggerEnabled,
        SiteAccess $siteAccess
    ) {
        $this->tokenStorage       = $tokenStorage;
        $this->repository         = $repository;
        $this->request            = $requestStack->getCurrentRequest();
        $this->tagsService        = $tagsService;
        $this->adminLoggerEnabled = (bool)$adminLoggerEnabled;
        $this->logDir = $logDir;
        $this->siteAccess = $siteAccess;
        $logHandler = new StreamHandler(
            sprintf(
                "%s/log_%s-%s.log",
                $this->logDir,
                $this->siteAccess->name,
                date("Y-m-d")
            )
        );
        $logHandler->setFormatter(new SqliSimpleLogFormatter());

        $this->logger = new Logger('Log_' . $this->siteAccess->name);
        $this->logger->pushHandler($logHandler);
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     * The array keys are event names and the value can be:
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     * For instance:
     *  * ['eventName' => 'methodName']
     *  * ['eventName' => ['methodName', $priority]]
     *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
              TrashEvent::class =>  'logIfTrashSignal',
              PublishVersionEvent::class => 'logIfPublishVersionSignal',
              CopyContentEvent::class => 'logIfCopyContentSignal',
              HideContentEvent::class => 'logIfVisibilityContentSignal',
              RevealContentEvent::class => 'logIfVisibilityContentSignal',
              DeleteContentEvent::class => 'logIfDeleteContentSignal',
              CreateLocationEvent::class => 'logIfCreateLocationSignal',
              HideLocationEvent::class => 'logIfVisibilityLocationSignal',
              UnhideLocationEvent::class => 'logIfVisibilityLocationSignal',
              DeleteLocationEvent::class => 'logIfDeleteLocationSignal',
              CreateUserEvent::class => 'logIfUserSignal',
              UpdateUserEvent::class => 'logIfUserSignal',
              DeleteUserEvent::class => 'logIfUserSignal',
        ];

    }

    /**
     * @param PublishVersionEvent $publishVersionEvent
     */
    public function logIfPublishVersionSignal(PublishVersionEvent $publishVersionEvent)
    {
        if (!$this->adminLoggerEnabled || !$this->isAdminSiteAccess()) {
            return;
        }
        $content = $publishVersionEvent->getContent() ;
        $versionId = $publishVersionEvent->getVersionInfo()->versionNo;
        $this->logger->notice("Content publish :");
        $this->logUserInformations();
        if(empty($content)) {
            $this->logger->notice("  - content : not found");
            return;
        }
        $this->logger->notice("  - content name : " . $content->getName());
        $this->logger->notice("  - content id : " . $content->id);
        $this->logger->notice("  - content version : " . $versionId);
    }

    /**
     * @param CopyContentEvent $copyContentEvent
     */
    public function logIfCopyContentSignal(CopyContentEvent $copyContentEvent)
    {
        if (!$this->adminLoggerEnabled || !$this->isAdminSiteAccess()) {
            return;
        }
        $this->logger->notice("Copy Content2 :");
        $this->logUserInformations();
        $srcContentInfo = $copyContentEvent->getContentInfo();
        $dstParentLocationId = $copyContentEvent->getDestinationLocationCreateStruct()->parentLocationId;

        if(empty($srcContentInfo)) {
            $this->logger->notice("  - content : not found");
            return;
        }
        $this->logger->notice("  - source content name : " . $srcContentInfo->name);
        $this->logger->notice("  - source content id : " . $srcContentInfo->id);
        $this->logger->notice("  - source location id : " . $srcContentInfo->mainLocationId);
        $this->logger->notice("  - source content version : " . $srcContentInfo->currentVersionNo);
        $this->logger->notice("  - destination location id : " . $dstParentLocationId);
    }

    /**
     * @param DeleteContentEvent $deleteContentEvent
     */
    public function logIfDeleteContentSignal(DeleteContentEvent $deleteContentEvent)
    {
        if (!$this->adminLoggerEnabled || !$this->isAdminSiteAccess()) {
            return;
        }
        $this->logger->addNotice("Content delete :");
        $this->logUserInformations();
        $contentInfo = $deleteContentEvent->getContentInfo();
        $locations = $deleteContentEvent->getLocations();

        if (empty($content)) {
            $this->logger->error("  - content : not found, may be it was the latest location ?");
            return;
        }
        $this->logger->addNotice("  - content id : " . $contentInfo->id);
        $this->logger->addNotice("  - location ids : " . implode(',', $locations));
    }

    /**
     * @param CreateLocationEvent $createLocationEvent
     */
    public function logIfCreateLocationSignal(CreateLocationEvent $createLocationEvent)
    {
        if (!$this->adminLoggerEnabled || !$this->isAdminSiteAccess()) {
            return;
        }
        $this->logger->notice("Location create :");
        $this->logUserInformations();
        $location = $createLocationEvent->getLocation();
        $contentInfo = $createLocationEvent->getContentInfo();
        $newParentLocation = $createLocationEvent->getLocationCreateStruct()->parentLocationId;
        if (empty($contentInfo)) {
            $this->logger->error("  - content : not found");
            return;
        }

        $this->logger->notice("  - location id : " . $location->id);
        $this->logger->notice("  - content id : " . $contentInfo->id);
        $this->logger->notice("  - content name : " . $contentInfo->name);
        // New Parent
        $this->logger->notice("  - new parent location id : " . $newParentLocation);
        try {
            $newParentLocation = $this->repository->getLocationService()->loadLocation($newParentLocation);
            $this->logger->notice(
                "  - new parent content name : " . $newParentLocation->getContent()->getName()
            );
        } catch (\Exception $exception) {
            $this->logger->error("  - new parent content : not found");
        }
    }

    /**
     * @param DeleteLocationEvent $deleteLocationEvent
     */
    public function logIfDeleteLocationSignal(DeleteLocationEvent $deleteLocationEvent)
    {
        if (!$this->adminLoggerEnabled || !$this->isAdminSiteAccess()) {
            return;
        }
        $location = $deleteLocationEvent->getLocation();
        $this->logger->notice("Location delete :");
        $this->logUserInformations();

        if (empty($Location)) {
            $this->logger->error("  - Location : not found");
            return;
        }

        $this->logger->notice("  - location id : " . $location->id);
        $this->logger->notice("  - parent location id : " . $location->parentLocationId);
        $this->logger->notice("  - content id : " . $location->contentId);
        $this->logger->notice("  - content name : " . $location->getContentInfo()->name);
    }

    /**
     * @param HideLocationEvent|UnhideLocationEvent $event
     */
    public function logIfVisibilityLocationSignal($event)
    {
        if (!$this->adminLoggerEnabled || !$this->isAdminSiteAccess()) {
            return;
        }
        $actionName = null;
        if ($event instanceof HideLocationEvent) {
            $actionName = "hide";
        } elseif ($event instanceof UnhideLocationEvent) {
            $actionName = "unhide";
        }

        if (!is_null($actionName)) {
            $location = $event->getLocation();
            $this->logger->notice("Location $actionName :");
            $this->logUserInformations();
            if (empty($location)) {
                $this->logger->error("  - location : not found");
                return;
            }
            $this->logger->notice("  - location id : " . $location->id);
            $this->logger->notice("  - parent location id : " . $location->parentLocationId);
            $this->logger->notice("  - content id : " . $location->contentId);
            $this->logger->notice("  - content name : " . $location->getContentInfo()->name);
        }
    }

    /**
     * @param HideContentEvent|RevealContentEvent $event
     */
    public function logIfVisibilityContentSignal($event)
    {
        $action = null;
        if ($event instanceof RevealContentEvent) {
            $action = 'unhide';
        } elseif ($event instanceof HideContentEvent) {
            $action = 'Hide';
        }

        if (!$this->adminLoggerEnabled || !$this->isAdminSiteAccess() || $action === null) {
            return;
        }
        $contentInfo = $event->getContentInfo();
        $this->logger->notice("$action Content:");
        if(empty($contentInfo)) {
            $this->logger->notice("  - content : not found");
            return;
        }

        $this->logUserInformations();
        $this->logger->notice("  - content id : " . $contentInfo->id);
        $this->logger->notice("  - content name : " . $contentInfo->name);
        $this->logger->notice("  - content main location id: " . $contentInfo->mainLocationId);
        $this->logger->notice("  - content version : " . $contentInfo->currentVersionNo);
    }

    /**
     * @param CreateUserEvent|UpdateUserEvent|DeleteUserEvent $event
     */
    public function logIfUserSignal($event)
    {
        if (!$this->adminLoggerEnabled || !$this->isAdminSiteAccess()) {
            return;
        }
        $actionName = null;
        $actionName = $event instanceof UpdateUserEvent ? "update" : $actionName;
        $actionName = $event instanceof CreateUserEvent ? "creation" : $actionName;
        $actionName = $event instanceof DeleteUserEvent ? "delete" : $actionName;

        if (!is_null($actionName)) {
            $user = $event->getUser();
            $this->logger->notice("User $actionName :");
            $this->logUserInformations();
            if (empty($user)) {
                $this->logger->error("  - user : not found");
                return;
            }
            $this->logger->notice("  - user id : " . $user->id);
            $this->logger->notice("  - user name : " . $user->getName());
        }
    }

    /**
     * @param TrashEvent $trashEvent
     */
    public function logIfTrashSignal(TrashEvent $trashEvent)
    {
        if (!$this->adminLoggerEnabled || !$this->isAdminSiteAccess()) {
            return;
        }
        $content = $trashEvent->getTrashItem() ? $trashEvent->getTrashItem()->getContent() : null;
        $locationId = $trashEvent->getTrashItem() ? $trashEvent->getTrashItem()->id  : null;
        $parentLocation = $trashEvent->getTrashItem() ? $trashEvent->getTrashItem()->getParentLocation(): null;
        $this->logger->notice("Move to Trash :");
        $this->logUserInformations();

        if (empty($content)) {
            $this->logger->error("  - content : not found, may be it was the latest location ?");
            return;
        }

        $this->logger->notice("  - content id : " . $content->id);
        $this->logger->notice("  - content name : " . $content->getName());
        $this->logger->notice("  - content trashed : " . (int)$content->id);
        $this->logger->notice("  - location id : " . $locationId);
        if($parentLocation) {
            $this->logger->notice("  - parent location id : " .  $parentLocation->id);
            $this->logger->notice("  - parent content name : " . $parentLocation->getContentInfo()->name);
        } else {
            $this->logger->error("  - parent content : not found");
        }
    }

    /**
     * Log connected user informations
     */
    public function logUserInformations(): void
    {
        /** @var UserInterface $user */
        $user = $this->tokenStorage->getToken()->getUser();

        $this->logger->notice("  - IP : " . implode(',', $this->request->getClientIps()));
        $this->logger->notice(
            sprintf(
                "  - user name : %s [contentId=%s]",
                $user->getUsername(),
                $user->getAPIUser()->getUserId()
            )
        );
    }
}