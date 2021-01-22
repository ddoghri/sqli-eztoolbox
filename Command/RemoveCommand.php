<?php

namespace SQLI\EzToolboxBundle\Command;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Query;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RemoveCommand extends ContainerAwareCommand
{
    /** @var Repository */
    private $repository;
    /** @var ContentService */
    private $contentService;
    /** @var LocationService */
    private $locationService;

    public function initialize( InputInterface $input, OutputInterface $output )
    {
        $output->setDecorated( true );

        $this->repository      = $this->getContainer()->get( 'ezpublish.api.repository' );
        $this->contentService  = $this->getContainer()->get( 'ezpublish.api.service.content' );
        $this->locationService = $this->getContainer()->get( 'ezpublish.api.service.inner_location' );

        // Load and set Administrator User
        $administratorUser = $this->repository->getUserService()->loadUser( 14 );
        $this->repository->getPermissionResolver()->setCurrentUserReference( $administratorUser );
    }

    protected function configure()
    {
        $this->setName( 'sqli:object:remove' )
            ->setDescription( 'Remove a content, a location or all contents of a content type' )
            ->addOption( 'content', null, InputOption::VALUE_OPTIONAL, "ContentID to remove" )
            ->addOption( 'location', null, InputOption::VALUE_OPTIONAL, "LocationID to remove" )
            ->addOption( 'contenttype', null, InputOption::VALUE_OPTIONAL|InputOption::VALUE_REQUIRED, "Remove Contents of this Content Type identifier" );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        if( $contentID = intval( $input->getOption( 'content' ) ) )
        {
            $output->write( "Remove contentID $contentID : " );
            $this->removeContent( $output, $contentID );
        }
        if( $locationID = $input->getOption( 'location' ) )
        {
            $output->write( "Remove locationID $locationID : " );
            $this->removeLocation( $output, $locationID );
        }
        if( $contentType = $input->getOption( 'contenttype' ) )
        {
            $output->write( "Remove Contents of this Content Type $contentType : " );
            $this->removeContentTypeContents( $input, $output, $contentType );
        }
    }

    private function removeContent( OutputInterface $output, $contentID )
    {
        $content     = $this->contentService->loadContentInfo( $contentID );
        $contentName = $content->name;
        $this->contentService->deleteContent( $content );
        $output->writeln( "<info>" . $contentName . "</info>" );
    }

    private function removeLocation( OutputInterface $output, $locationID )
    {
        $location    = $this->locationService->loadLocation( $locationID );
        $contentName = $location->getContent()->getName();
        $this->locationService->deleteLocation( $location );
        $output->writeln( "<info>" . $contentName . "</info>" );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $contentTypeIdentifier
     * @throws InvalidArgumentException
     * @throws UnauthorizedException
     */
    private function removeContentTypeContents(InputInterface $input, OutputInterface $output, $contentTypeIdentifier)
    {
        $query         = new Query();
        $query->query  = new Query\Criterion\ContentTypeIdentifier($contentTypeIdentifier);
        $searchResults = $this->repository->getSearchService()->findContent($query);
        $totalCount    = $searchResults->totalCount;
        unset($searchResults);

        $output->writeln(sprintf("Number of contents to remove : <info>%s</info>", $totalCount));
        $this->askConfirmation($input, $output);

        $offset = 0;
        while ($offset <= $totalCount) {
            $searchResults = $this->repository->getSearchService()->findContent($query);
            foreach ($searchResults->searchHits as $searchHit) {
                /** @var Content $content */
                $content = $searchHit->valueObject;
                $output->write($content->getName() . " : ");
                $this->repository->getContentService()->deleteContent($content->contentInfo);
                $output->writeln("<info>deleted</info>");
            }
            unset($searchResults);

            $offset += $query->limit;
        }

        $output->writeln("<comment>All contents removed</comment>");
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    private function askConfirmation(InputInterface $input, OutputInterface $output)
    {
        // Ask confirmation
        $output->writeln("");
        $helper   = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            '<question>Are you sure you want to proceed [y/N]?</question> ',
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('');

            exit;
        }
    }
}