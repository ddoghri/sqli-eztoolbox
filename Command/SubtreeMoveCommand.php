<?php

namespace SQLI\EzToolboxBundle\Command;

use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\Core\QueryType\QueryTypeRegistry;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SubtreeMoveCommand extends ContainerAwareCommand
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
    private $currentLocationID;
    /** @var int */
    private $newParentLocationID;

    protected function configure()
    {
        $this->setName( 'sqli:move:subtree' )
            ->setDescription( 'Move "currentParentLocationID" and it\'s subtree under "newParentLocationID"' )
            ->addArgument( 'current', InputArgument::REQUIRED, "Move this locationID and it's subtree" )
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
        $currentLocation = $this->locationService->loadLocation( $this->currentLocationID );
        // Retrieve new Location
        $newLocation = $this->locationService->loadLocation( $this->newParentLocationID );

        // Information
        $output->writeln( sprintf( "Move <comment>%s</comment> under <comment>%s</comment>", $currentLocation->getContentInfo()->name, $newLocation->getContentInfo()->name ) );
        $output->writeln( "" );

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

        $output->write( sprintf( "[locationID : %s] <comment>%s</comment> moved ", $currentLocation->id, $currentLocation->getContentInfo()->name ) );
        $this->locationService->moveSubtree( $currentLocation, $newLocation );
        $output->writeln( "<info>[OK]</info>" );

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

        $this->currentLocationID = (int)$input->getArgument( 'current' );
        $this->newParentLocationID     = (int)$input->getArgument( 'new' );

        // Load and set Administrator User
        $administratorUser = $this->repository->getUserService()->loadUser( 14 );
        $this->repository->getPermissionResolver()->setCurrentUserReference( $administratorUser );
    }
}
