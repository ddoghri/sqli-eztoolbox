<?php

namespace SQLI\EzToolboxBundle\Services\Listener;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\Core\MVC\Symfony\Event\SignalEvent;
use eZ\Publish\Core\MVC\Symfony\MVCEvents;
use eZ\Publish\Core\MVC\Symfony\Security\UserInterface;
use eZ\Publish\Core\MVC\Symfony\SiteAccess;
use eZ\Publish\Core\SignalSlot\Signal;
use eZ\Publish\Core\SignalSlot\Signal\ContentService\PublishVersionSignal;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Netgen\TagsBundle\Core\Repository\TagsService;
use Netgen\TagsBundle\Core\SignalSlot\Signal\TagsService\CreateTagSignal;
use Netgen\TagsBundle\Core\SignalSlot\Signal\TagsService\DeleteTagSignal;
use Netgen\TagsBundle\Core\SignalSlot\Signal\TagsService\UpdateTagSignal;
use SQLI\EzToolboxBundle\Services\Formatter\SqliSimpleLogFormatter;
use SQLI\EzToolboxBundle\Services\SiteAccessUtilsTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class BackOfficeActionsLoggerListener implements EventSubscriberInterface
{
    use SiteAccessUtilsTrait;

    /** @var TokenStorage */
    private $tokenStorage;
    /** @var Repository */
    private $repository;
    /** @var Logger */
    private $logger;
    /** @var Request */
    private $request;
    /** @var TagsService */
    private $tagsService;
    /** @var bool */
    private $adminLoggerEnabled;

    public function __construct(
        TokenStorage $tokenStorage,
        Repository $repository,
        $logDir,
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

        // Handler and formatter
        $logHandler = new StreamHandler(
            sprintf(
                "%s/log_%s-%s.log",
                $logDir,
                $siteAccess->name,
                date("Y-m-d")
            )
        );
        $logHandler->setFormatter(new SqliSimpleLogFormatter());

        $this->logger = new Logger('Log_' . $siteAccess->name);
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
            //MVCEvents::API_SIGNAL => 'onAPISignal',
        ];
    }

    /**
     * @param SignalEvent $event
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function onAPISignal(SignalEvent $event)
    {
        // Log only for admin siteaccesses
        if (!$this->adminLoggerEnabled || !$this->isAdminSiteAccess()) {
            return;
        }

        $signal = $event->getSignal();

        // Content signals
        $this->logIfPublishVersionSignal($signal);
        $this->logIfCopyContentSignal($signal);
        $this->logIfVisibilityContentSignal($signal);
        $this->logIfDeleteContentSignal($signal);
        // Location signals
        $this->logIfCreateLocationSignal($signal);
        $this->logIfCopySubtreeSignal($signal);
        $this->logIfMoveSubtreeSignal($signal);
        $this->logIfVisibilityLocationSignal($signal);
        $this->logIfDeleteLocationSignal($signal);
        // Tag signals
        $this->logIfCreateTagSignal($signal);
        $this->logIfUpdateTagSignal($signal);
        $this->logIfDeleteTagSignal($signal);
        // User signals
        $this->logIfUserSignal($signal);
        // Trash signals
        $this->logIfTrashSignal($signal);
        // Assign section
        $this->logIfAssignSectionSignal($signal);
        // Object State
        $this->logIfSetContentStateSignal($signal);
    }

    /**
     * @param $signal Signal
     */
    private function logIfPublishVersionSignal($signal)
    {
        if ($signal instanceof PublishVersionSignal) {
            $contentId = $signal->contentId;
            $versionId = $signal->versionNo;
            $this->logger->addNotice("Content publish :");
            $this->logUserInformations();
            try {
                $content = $this->repository->getContentService()->loadContent($contentId, [], $versionId);
                $this->logger->addNotice("  - content name : " . $content->getName());
            } catch (\Exception $exception) {
                $this->logger->addError("  - content : not found");
            }
            $this->logger->addNotice("  - content id : " . $contentId);
            $this->logger->addNotice("  - content version : " . $versionId);
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfCopyContentSignal($signal)
    {
        if ($signal instanceof Signal\ContentService\CopyContentSignal) {
            $srcContentId = $signal->srcContentId;
            $srcVersionId = $signal->srcVersionNo;
            $dstContentId = $signal->dstContentId;
            $dstVersionId = $signal->dstVersionNo;
            $this->logger->addNotice("Content publish :");
            $this->logUserInformations();
            try {
                $srcContent = $this->repository->getContentService()->loadContent($srcContentId, [], $srcVersionId);
                $this->logger->addNotice("  - content name : " . $srcContent->getName());
            } catch (\Exception $exception) {
                $this->logger->addError("  - content : not found");
            }
            $this->logger->addNotice("  - original content id : " . $srcContentId);
            $this->logger->addNotice("  - original content version : " . $srcVersionId);
            $this->logger->addNotice("  - destination content id : " . $dstContentId);
            $this->logger->addNotice("  - destination content version : " . $dstVersionId);

            try {
                $dstParentLocationId = $signal->dstParentLocationId;
                $dstParentLocation   = $this->repository->getLocationService()->loadLocation($dstParentLocationId);
                $this->logger->addNotice("  - destination parent location id : " . $dstParentLocationId);
                $this->logger->addNotice(
                    "  - destination parent content name : " . $dstParentLocation->getContent()->getName()
                );
            } catch (\Exception $exception) {
                $this->logger->addError("  - destination parent location : not found");
            }
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfDeleteContentSignal($signal)
    {
        if ($signal instanceof Signal\ContentService\DeleteContentSignal) {
            $this->logger->addNotice("Content delete :");
            $this->logUserInformations();
            $this->logger->addNotice("  - content id : " . $signal->contentId);
            $this->logger->addNotice("  - location ids : " . implode(',', $signal->affectedLocationIds));
        }
    }

    /**
     * @param $signal Signal
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    private function logIfCreateTagSignal($signal)
    {
        if ($signal instanceof CreateTagSignal) {
            $parentTagName = "no parent";
            if ($signal->parentTagId != 0) {
                $parentTagName = $this->tagsService->loadTag($signal->parentTagId)->getKeyword();
            }
            $this->logger->addNotice("Tag creation :");
            $this->logUserInformations();
            $this->logger->addNotice("  - tag id : " . $signal->tagId);
            $this->logger->addNotice("  - tag name : " . $signal->keywords[$signal->mainLanguageCode]);
            $this->logger->addNotice("  - tag parent id : " . $signal->parentTagId);
            $this->logger->addNotice("  - tag parent name : " . $parentTagName);
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfUpdateTagSignal($signal)
    {
        if ($signal instanceof UpdateTagSignal) {
            $this->logger->addNotice("Tag update :");
            $this->logUserInformations();
            $this->logger->addNotice("  - tag id : " . $signal->tagId);
            $this->logger->addNotice("  - new tag name : " . $signal->keywords[$signal->mainLanguageCode]);
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfDeleteTagSignal($signal)
    {
        if ($signal instanceof DeleteTagSignal) {
            $this->logger->addNotice("Tag delete :");
            $this->logUserInformations();
            $this->logger->addNotice("  - tag id : " . $signal->tagId);
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfMoveSubtreeSignal($signal)
    {
        if ($signal instanceof Signal\LocationService\MoveSubtreeSignal) {
            $this->logger->addNotice("Location move :");
            $this->logUserInformations();
            $this->logger->addNotice("  - location id : " . $signal->locationId);

            // Old parent
            $this->logger->addNotice("  - old parent location id : " . $signal->oldParentLocationId);
            try {
                $oldParentLocation = $this->repository->getLocationService()->loadLocation(
                    $signal->oldParentLocationId
                );
                $this->logger->addNotice(
                    "  - old parent content name : " . $oldParentLocation->getContent()->getName()
                );
            } catch (\Exception $exception) {
                $this->logger->addError("  - old parent content : not found");
            }

            // New parent
            $this->logger->addNotice("  - new parent location id : " . $signal->newParentLocationId);
            try {
                $newParentLocation = $this->repository->getLocationService()->loadLocation(
                    $signal->newParentLocationId
                );
                $this->logger->addNotice(
                    "  - new parent content name : " . $newParentLocation->getContent()->getName()
                );
            } catch (\Exception $exception) {
                $this->logger->addError("  - new parent content : not found");
            }
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfCopySubtreeSignal($signal)
    {
        if ($signal instanceof Signal\LocationService\CopySubtreeSignal) {
            $this->logger->addNotice("Location copy :");
            $this->logUserInformations();

            // Original parent
            $this->logger->addNotice("  - original location id : " . $signal->subtreeId);
            try {
                $originalLocation = $this->repository->getLocationService()->loadLocation($signal->subtreeId);
                $this->logger->addNotice("  - original content name : " . $originalLocation->getContent()->getName());
            } catch (\Exception $exception) {
                $this->logger->addError("  - original content : not found");
            }

            // New Parent
            $this->logger->addNotice("  - copy's parent location id : " . $signal->targetParentLocationId);
            try {
                $newParentLocation = $this->repository->getLocationService()->loadLocation(
                    $signal->targetParentLocationId
                );
                $this->logger->addNotice(
                    "  - copy's parent content name : " . $newParentLocation->getContent()->getName()
                );
            } catch (\Exception $exception) {
                $this->logger->addError("  - copy's parent content : not found");
            }

            // New Location
            $this->logger->addNotice("  - copy's location id : " . $signal->targetNewSubtreeId);
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfCreateLocationSignal($signal)
    {
        if ($signal instanceof Signal\LocationService\CreateLocationSignal) {
            $this->logger->addNotice("Location create :");
            $this->logUserInformations();

            $this->logger->addNotice("  - location id : " . $signal->locationId);
            $this->logger->addNotice("  - content id : " . $signal->contentId);
            try {
                $content = $this->repository->getContentService()->loadContent($signal->contentId);
                $this->logger->addNotice("  - content name : " . $content->getName());
            } catch (\Exception $exception) {
                $this->logger->addError("  - content : not found");
            }

            // New Parent
            $this->logger->addNotice("  - new parent location id : " . $signal->parentLocationId);
            try {
                $newParentLocation = $this->repository->getLocationService()->loadLocation($signal->parentLocationId);
                $this->logger->addNotice(
                    "  - new parent content name : " . $newParentLocation->getContent()->getName()
                );
            } catch (\Exception $exception) {
                $this->logger->addError("  - new parent content : not found");
            }
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfDeleteLocationSignal($signal)
    {
        if ($signal instanceof Signal\LocationService\DeleteLocationSignal) {
            $this->logger->addNotice("Location delete :");
            $this->logUserInformations();
            $this->logger->addNotice("  - location id : " . $signal->locationId);
            $this->logger->addNotice("  - parent location id : " . $signal->parentLocationId);
            $this->logger->addNotice("  - content id : " . $signal->contentId);
            try {
                $content = $this->repository->getContentService()->loadContent($signal->contentId);
                $this->logger->addNotice("  - content name : " . $content->getName());
            } catch (\Exception $exception) {
                $this->logger->addNotice("  - content : not found, may be it was the latest location ?");
            }
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfVisibilityLocationSignal($signal)
    {
        $actionName = null;
        if ($signal instanceof Signal\LocationService\HideLocationSignal) {
            $actionName = "hide";
        }
        if ($signal instanceof Signal\LocationService\UnhideLocationSignal) {
            $actionName = "unhide";
        }
        if (!is_null($actionName)) {
            $this->logger->addNotice("Location $actionName :");
            $this->logUserInformations();
            $this->logger->addNotice("  - location id : " . $signal->locationId);
            $this->logger->addNotice("  - parent location id : " . $signal->parentLocationId);
            $this->logger->addNotice("  - content id : " . $signal->contentId);
            try {
                $content = $this->repository->getContentService()->loadContent($signal->contentId);
                $this->logger->addNotice("  - content name : " . $content->getName());
            } catch (\Exception $exception) {
                $this->logger->addError("  - content : not found");
            }
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfVisibilityContentSignal($signal)
    {
        $actionName = null;
        if ($signal instanceof Signal\ContentService\HideContentSignal) {
            $actionName = "hide";
        }
        if ($signal instanceof Signal\ContentService\RevealContentSignal) {
            $actionName = "unhide";
        }
        if (!is_null($actionName)) {
            $this->logger->addNotice("Content $actionName :");
            $this->logUserInformations();
            $this->logger->addNotice("  - content id : " . $signal->contentId);
            try {
                $content = $this->repository->getContentService()->loadContent($signal->contentId);
                $this->logger->addNotice("  - content name : " . $content->getName());
            } catch (\Exception $exception) {
                $this->logger->addError("  - content : not found");
            }
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfUserSignal($signal)
    {
        $actionName = null;
        $actionName = $signal instanceof Signal\UserService\UpdateUserSignal ? "update" : $actionName;
        $actionName = $signal instanceof Signal\UserService\CreateUserSignal ? "creation" : $actionName;
        $actionName = $signal instanceof Signal\UserService\DeleteUserSignal ? "delete" : $actionName;

        if (!is_null($actionName)) {
            $this->logger->addNotice("User $actionName :");
            $this->logUserInformations();
            $this->logger->addNotice("  - user id : " . $signal->userId);
            try {
                $user = $this->repository->getUserService()->loadUser($signal->userId);
                $this->logger->addNotice("  - user name : " . $user->getName());
            } catch (\Exception $exception) {
                if (!$signal instanceof Signal\UserService\DeleteUserSignal) {
                    $this->logger->addError("  - content : not found");
                }
            }
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfTrashSignal($signal)
    {
        if ($signal instanceof Signal\TrashService\TrashSignal) {
            $this->logger->addNotice("Trash :");
            $this->logUserInformations();
            $this->logger->addNotice("  - content id : " . $signal->contentId);
            try {
                $content = $this->repository->getContentService()->loadContent($signal->contentId);
                $this->logger->addNotice("  - content name : " . $content->getName());
            } catch (\Exception $exception) {
                $this->logger->addNotice("  - content : not found, may be it was the latest location ?");
            }
            $this->logger->addNotice("  - content trashed : " . (int)$signal->contentTrashed);
            $this->logger->addNotice("  - location id : " . $signal->locationId);
            $this->logger->addNotice("  - parent location id : " . $signal->parentLocationId);
            try {
                $location = $this->repository->getLocationService()->loadLocation($signal->parentLocationId);
                $this->logger->addNotice("  - parent content name : " . $location->getContent()->getName());
            } catch (\Exception $exception) {
                $this->logger->addError("  - parent content : not found");
            }
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfAssignSectionSignal($signal)
    {
        if ($signal instanceof Signal\SectionService\AssignSectionToSubtreeSignal) {
            $this->logger->addNotice("Assign section :");
            $this->logUserInformations();
            $this->logger->addNotice("  - location id : " . $signal->locationId);
            try {
                $location = $this->repository->getLocationService()->loadLocation($signal->locationId);
                $this->logger->addNotice("  - location name : " . $location->getContent()->getName());
            } catch (\Exception $exception) {
                $this->logger->addError("  - location not found");
            }

            $this->logger->addNotice("  - section id : " . $signal->sectionId);
            try {
                $section = $this->repository->getSectionService()->loadSection($signal->sectionId);
                $this->logger->addNotice("  - section name : " . $section->name);
            } catch (\Exception $exception) {
                $this->logger->addError("  - section not found");
            }
        }
    }

    /**
     * @param $signal Signal
     */
    private function logIfSetContentStateSignal($signal)
    {
        if ($signal instanceof Signal\ObjectStateService\SetContentStateSignal) {
            $this->logger->addNotice("Change object state :");
            $this->logUserInformations();
            $this->logger->addNotice("  - content id : " . $signal->contentId);
            // Content
            try {
                $content = $this->repository->getContentService()->loadContent($signal->contentId);
                $this->logger->addNotice("  - content name : " . $content->getName());
            } catch (\Exception $exception) {
                $this->logger->addError("  - content not found");
            }
            // Object state group
            $this->logger->addNotice("  - object state group id : " . $signal->objectStateGroupId);
            try {
                $objectStateGroup = $this->repository->getObjectStateService()->loadObjectStateGroup(
                    $signal->objectStateGroupId
                );
                $this->logger->addNotice("  - object state group name : " . $objectStateGroup->getName());
            } catch (\Exception $exception) {
                $this->logger->addError("  - object state not found");
            }
            // Object state
            $this->logger->addNotice("  - object state id : " . $signal->objectStateId);
            try {
                $objectState = $this->repository->getObjectStateService()->loadObjectState($signal->objectStateId);
                $this->logger->addNotice("  - object state name : " . $objectState->getName());
            } catch (\Exception $exception) {
                $this->logger->addError("  - object state not found");
            }
        }
    }

    /**
     * Log connected user informations
     */
    private function logUserInformations(): void
    {
        /** @var UserInterface $user */
        $user = $this->tokenStorage->getToken()->getUser();

        $this->logger->addNotice("  - IP : " . implode(',', $this->request->getClientIps()));
        $this->logger->addNotice(
            sprintf(
                "  - user name : %s [contentId=%s]",
                $user->getUsername(),
                $user->getAPIUser()->getUserId()
            )
        );
    }
}