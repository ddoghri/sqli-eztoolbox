<?php

namespace SQLI\EzToolboxBundle\Command;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\VersionInfo;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PurgeDraftsCommand extends ContainerAwareCommand
{
    const FETCH_LIMIT = 25;
    /** @var ContentService */
    private $contentService;
    /** @var SearchService */
    private $searchService;

    public function initialize( InputInterface $input, OutputInterface $output )
    {
        $output->setDecorated( true );

        $this->contentService = $this->getContainer()->get( 'ezpublish.api.service.content' );
        $this->searchService  = $this->getContainer()->get( 'ezpublish.api.service.inner_search' );

        // Load and set Administrator User for permissions
        $administratorUser = $this->getContainer()->get( 'ezpublish.api.repository' )->getUserService()->loadUser( 14 );
        $this->getContainer()->get( 'ezpublish.api.repository' )->getPermissionResolver()->setCurrentUserReference( $administratorUser );
    }

    protected function configure()
    {
        $this->setName( 'sqli:purge:drafts' )
            ->setDescription( 'Purge orphan drafts' );
    }

    /**
     * Purge orphan drafts
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        // Count all contents
        $query               = new Query();
        $query->offset       = 0;
        $query->limit        = 0;
        $query->query        = new Criterion\MatchAll();
        $query->performCount = true;
        $findResults         = $this->searchService->findContentInfo( $query );
        $totalCount          = $findResults->totalCount;
        $index               = 1;

        $output->writeln( "Number of contents to scan : <info>{$totalCount}</info>" );

        // Fetch contents with offset and limit
        while( $query->offset <= $totalCount )
        {
            $query->limit        = self::FETCH_LIMIT;
            $query->performCount = false;

            // Fetch content infos
            $findResults = $this->searchService->findContentInfo( $query );

            foreach( $findResults->searchHits as $hitResult )
            {
                $contentInfo = $hitResult->valueObject;
                /** @var ContentInfo $contentInfo */
                // Retrieve all versions for each contentInfo
                $versions = $this->contentService->loadVersions( $contentInfo );
                // Check each versions
                foreach( $versions as $version )
                {
                    /** @var VersionInfo $version */
                    $date = new \DateTime( '-1 week' );
                    // If version is a draft and modification date is greater than 1 week then it's an orphan draft
                    if( $version->isDraft() && $version->modificationDate < $date )
                    {
                        $output->writeln( "[{$index}/{$totalCount}] ContentID {$contentInfo->id} : <comment>{$contentInfo->name}</comment>" );
                        $output->write( "     - DraftID {$version->id} " );

                        // Remove old draft
                        $output->write( "<comment>must be purged</comment> : " );

                        $success = false;
                        try
                        {
                            // Try to delete orphan version
                            $this->contentService->deleteVersion( $version );
                            $success = true;
                        }
                        catch( \Exception $exception )
                        {
                            // Do nothing
                        }

                        if( $success )
                        {
                            $output->write( "<info>OK</info>" );
                        }
                        else
                        {
                            $output->write( "<error>FAILED !</error>" );
                        }
                        $output->writeln( "" );
                    }
                }
                $index++;
            }

            // Modify offset for next iteration
            $query->offset += self::FETCH_LIMIT;
        }
    }
}