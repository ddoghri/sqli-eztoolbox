<?php

namespace SQLI\EzToolboxBundle\Command;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\SearchService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PurgeTrashCommand extends ContainerAwareCommand
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
        $this->setName( 'sqli:purge:trash' )
            ->setDescription( 'Purge eZ trash' );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $trashServices = $this->getContainer()->get( 'ezpublish.api.service.trash' );
        $trashServices->emptyTrash();

        $output->writeln( "Trash emptied" );
    }
}