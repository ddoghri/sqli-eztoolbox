<?php

namespace SQLI\EzToolboxBundle\Command;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\LocationService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCommand extends ContainerAwareCommand
{
    /** @var ContentService */
    private $contentService;
    /** @var LocationService */
    private $locationService;

    public function initialize( InputInterface $input, OutputInterface $output )
    {
        $output->setDecorated( true );

        $repository            = $this->getContainer()->get( 'ezpublish.api.repository' );
        $this->contentService  = $this->getContainer()->get( 'ezpublish.api.service.content' );
        $this->locationService = $this->getContainer()->get( 'ezpublish.api.service.inner_location' );

        // Load and set Administrator User
        $administratorUser = $repository->getUserService()->loadUser( 14 );
        $repository->getPermissionResolver()->setCurrentUserReference( $administratorUser );
    }

    protected function configure()
    {
        $this->setName( 'sqli:object:remove' )
            ->setDescription( 'Remove a content or a location' )
            ->addOption( 'content', null, InputOption::VALUE_OPTIONAL, "ContentID to remove" )
            ->addOption( 'location', null, InputOption::VALUE_OPTIONAL, "LocationID to remove" );
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
}