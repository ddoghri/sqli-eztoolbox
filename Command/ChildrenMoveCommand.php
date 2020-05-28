<?php

namespace SQLI\EzToolboxBundle\Command;

use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\Core\QueryType\QueryTypeRegistry;
use eZ\Publish\Core\Repository\SearchService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ChildrenMoveCommand extends ContainerAwareCommand
{
    /** @var Repository */
    private $repository;
    /** @var LocationService */
    private $locationService;
    /** @var QueryTypeRegistry */
    private $queryTypeRegistry;
    /** @var SearchService */
    private $searchService;

    /** @var int */
    private $currentParentLocationID;
    /** @var int */
    private $newParentLocationID;

    protected function configure()
    {
        $this->setName( 'sqli:move:children' )
            ->setDescription( 'Move all children of "currentParentLocationID" under "newParentLocationID"' )
            ->addArgument( 'current', InputArgument::REQUIRED, "Move children of this locationID" )
            ->addArgument( 'new', InputArgument::REQUIRED, "Move children under this locationID" );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        // Retrieve current Location
        $currentLocation = $this->locationService->loadLocation( $this->currentParentLocationID );
        // Retrieve new Location
        $newLocation = $this->locationService->loadLocation( $this->newParentLocationID );

        // Information
        $output->writeln( sprintf( "Move children of <comment>%s</comment> under <comment>%s</comment>", $currentLocation->getContentInfo()->name, $newLocation->getContentInfo()->name ) );
        $output->writeln( "" );

        // Retrieve children to move
        $childrenQueryType = $this->queryTypeRegistry->getQueryType( 'SQLI:LocationChildren' );
        /** @var LocationQuery $childrenQuery */
        $childrenQuery  = $childrenQueryType->getQuery( [ 'parent_location_id' => $this->currentParentLocationID ] );
        $childrenToMove = $this->searchService->findLocations( $childrenQuery );

        $output->writeln( "Task list :" );
        foreach( $childrenToMove->searchHits as $childToMove )
        {
            /** @var $childToMove Location */
            $output->writeln( sprintf( "[locationID : %s] <comment>%s</comment> will be moved", $childToMove->id, $childToMove->getContentInfo()->name ) );
        }

        // Ask confirmation
        $output->writeln( "" );
        $helper   = $this->getHelper( 'question' );
        $question = new ConfirmationQuestion(
            '<question>Are you sure you want to proceed [y/N]?</question> ',
            false
        );

        if( !$helper->ask( $input, $output, $question ) )
        {
            $output->writeln( '' );

            return;
        }

        $output->writeln( "" );
        $output->writeln( "Starting job :" );

        // Move each child
        foreach( $childrenToMove->searchHits as $childToMove )
        {
            /** @var $childToMove Location */
            $output->write( sprintf( "[locationID : %s] <comment>%s</comment> moved ", $childToMove->id, $childToMove->getContentInfo()->name ) );
            $this->locationService->moveSubtree( $childToMove, $newLocation );
            $output->writeln( "<info>[OK]</info>" );
        }

        $output->writeln( "" );
        $output->writeln( "<info>Job finished !</info>" );
    }

    protected function initialize( InputInterface $input, OutputInterface $output )
    {
        $this->queryTypeRegistry = $this->getContainer()->get( 'ezpublish.query_type.registry' );
        $this->searchService     = $this->getContainer()->get( 'ezpublish.api.service.inner_search' );
        $this->repository        = $this->getContainer()->get( 'ezpublish.api.repository' );
        $this->locationService   = $this->repository->getLocationService();

        $output->setDecorated( true );
        $input->setInteractive( true );

        $this->currentParentLocationID = (int)$input->getArgument( 'current' );
        $this->newParentLocationID     = (int)$input->getArgument( 'new' );

        // Load and set Administrator User
        $administratorUser = $this->repository->getUserService()->loadUser( 14 );
        $this->repository->getPermissionResolver()->setCurrentUserReference( $administratorUser );
    }
}
