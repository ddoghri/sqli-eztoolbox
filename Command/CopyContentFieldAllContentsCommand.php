<?php

namespace SQLI\EzToolboxBundle\Command;

use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause\ContentId;
use eZ\Publish\Core\FieldType\DateAndTime\Value;
use eZ\Publish\Core\Repository\Repository;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CopyContentFieldAllContentsCommand extends ContainerAwareCommand
{
    const FETCH_LIMIT = 25;
    private $contentClassIdentifier;
    private $oldContentFieldIdentifier;
    private $newContentFieldIdentifier;
    private $dryrun;
    /** @var Repository */
    private $repository;
    /** @var SearchService */
    private $searchService;
    /** @var \eZ\Publish\API\Repository\ContentService */
    private $contentService;
    private $totalCount;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function initialize( InputInterface $input, OutputInterface $output )
    {
        $output->setDecorated( true );
        $input->setInteractive( true );

        $this->contentClassIdentifier    = $input->getArgument( 'contentClassIdentifier' );
        $this->oldContentFieldIdentifier = $input->getArgument( 'oldContentFieldIdentifier' );
        $this->newContentFieldIdentifier = $input->getArgument( 'newContentFieldIdentifier' );
        $this->dryrun                    = $input->hasParameterOption( array(
                                                                           '--dry-run',
                                                                           '-d'
                                                                       ), true );

        $this->repository     = $this->getContainer()->get( 'ezpublish.api.repository' );
        $this->searchService  = $this->repository->getSearchService();
        $this->contentService = $this->repository->getContentService();

        // Load and set Administrator User for permissions
        $administratorUser = $this->repository->getUserService()->loadUser( 14 );
        $this->repository->getPermissionResolver()->setCurrentUserReference( $administratorUser );

        // Count number of contents to update
        $this->totalCount = $this->fetchCount();
    }

    protected function configure()
    {
        $this->setName( 'sqli:object:field_copy' )
            ->setDescription( "Copy value of a ContentField to a new ContentField for all existing contents of specific ContentType\nWARNING : Specific types not yet supported (XML, Image, ...)" )
            ->addArgument( 'contentClassIdentifier', InputArgument::REQUIRED, "ContentType identifier" )
            ->addArgument( 'oldContentFieldIdentifier', InputArgument::REQUIRED, "Original ContentField identifier" )
            ->addArgument( 'newContentFieldIdentifier', InputArgument::REQUIRED, "Target ContentField identifier" )
            ->addOption( 'dry-run', 'd', InputOption::VALUE_NONE, "Simulation mode" );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $output->writeln( "Fetching all objects of contentType '<comment>{$this->contentClassIdentifier}</comment>'" );

        // Informations
        $output->writeln( "<comment>{$this->totalCount}</comment> contents found" );

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

        $availableLanguages = $this->repository->getContentLanguageService()->loadLanguages();

        foreach( $availableLanguages as $availableLanguage )
        {
            $availableLanguageCode = $availableLanguage->languageCode;
            $output->writeln( "Starting job for <info>$availableLanguageCode</info> :" );
            $this->totalCount = $this->fetchCount( $availableLanguageCode );
            $offset           = 0;

            do
            {
                // Fetch small group of contents
                $items = $this->fetch( self::FETCH_LIMIT, $offset, $availableLanguageCode );

                // Publish each content
                foreach( $items as $index => $content )
                {
                    /** @var $content Content */
                    $output->write( sprintf( "[%s/%s][%s] contentID: %s <comment>%s</comment> ", ( $offset + $index + 1 ), $this->totalCount, $availableLanguageCode, $content->id, $content->getName() ) );

                    // Create draft
                    $contentDraft = $this->contentService->createContentDraft( $content->getVersionInfo()->getContentInfo() );
                    // Prepare update
                    $contentStructure = $this->contentService->newContentUpdateStruct();

                    // Get value of old field
                    switch( $contentDraft->getField( $this->oldContentFieldIdentifier, $availableLanguageCode )->fieldTypeIdentifier )
                    {
                        case "ezdate":
                            /** @var \eZ\Publish\Core\FieldType\Date\Value $fieldValue */
                            $fieldValue = $contentDraft->getFieldValue( $this->oldContentFieldIdentifier, $availableLanguageCode );
                            $valueToCopy = $fieldValue->date;
                            break;
                        case "ezdatetime":
                            /** @var Value $fieldValue */
                            $fieldValue = $contentDraft->getFieldValue( $this->oldContentFieldIdentifier, $availableLanguageCode );
                            $valueToCopy = $fieldValue->value;
                            break;
                        default:
                            $valueToCopy = $contentDraft->getFieldValue( $this->oldContentFieldIdentifier, $availableLanguageCode )->__toString();
                            break;
                    }

                    $update = true;
                    // Format data value according to field type
                    switch( $contentDraft->getField( $this->newContentFieldIdentifier, $availableLanguageCode )->fieldTypeIdentifier )
                    {
                        case "ezrichtext":
                            // @see : https://github.com/ezsystems/ezplatform-xmltext-fieldtype/blob/master/bundle/Command/ConvertXmlTextToRichTextCommand.php
                            $valueToCopy = "<section xmlns=\"http://ez.no/namespaces/ezpublish5/xhtml5/edit\"><p>{$valueToCopy}</p></section>";
                            $valueToCopy = $this->getContainer()->get( 'ezpublish.fieldType.ezrichtext' )->acceptValue( $valueToCopy );

                            $oldValueInNewField = $contentDraft->getFieldValue( $this->newContentFieldIdentifier, $availableLanguageCode )->__toString();
                            if( $valueToCopy == $oldValueInNewField )
                            {
                                $update = false;
                            }
                            break;
                        case "eztagco":
                            $contentJson = json_decode( $valueToCopy );
                            if( !is_null( $contentJson ) && property_exists( $contentJson, 'url' ) )
                            {
                                $valueToCopy = $contentJson->url;
                            }
                            else
                            {
                                continue 2;
                            }
                            break;
                    }

                    if( $update )
                    {
                        if( !$this->dryrun )
                        {
                            // Set value on new field
                            $contentStructure->setField( $this->newContentFieldIdentifier, $valueToCopy, $availableLanguageCode );

                            // Update draft
                            $contentDraft = $this->contentService->updateContent( $contentDraft->getVersionInfo(), $contentStructure );
                            // Publish draft
                            $this->contentService->publishVersion( $contentDraft->getVersionInfo() );
                        }

                        $output->writeln( "modified" );
                    }
                    else
                    {
                        $output->writeln( "" );
                    }
                }

                $offset += self::FETCH_LIMIT;
            } while( $offset < $this->totalCount );
            $output->writeln( "" );
        }

        if( $this->dryrun )
        {
            $output->writeln( "<question>Mode dry-run, no content updated</question>" );
        }
        $output->writeln( "" );
        $output->writeln( "<info>Job finished !</info>" );
    }

    /**
     * Returns number of contents who will be updated
     *
     * @return mixed
     */
    private function fetchCount( $languageCode = "fre-FR" )
    {
        $this->searchService = $this->repository->getSearchService();

        // Prepare count
        $query = new LocationQuery();

        $query->query        = new Criterion\LogicalAnd( [
                                                             new Criterion\ContentTypeIdentifier( $this->contentClassIdentifier ),
                                                             new Criterion\LanguageCode( $languageCode, false ),
                                                         ] );
        $query->performCount = true;
        $query->limit        = 0;
        $results             = $this->searchService->findContent( $query );

        return $results->totalCount;
    }

    /**
     * Fetch contents with offset and limit
     *
     * @param        $limit
     * @param int    $offset
     * @param string $languageCode
     * @return array
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    private function fetch( $limit, $offset = 0, $languageCode = "fre-FR" )
    {
        $this->searchService = $this->repository->getSearchService();

        // Prepare fetch with offset and limit
        $query = new LocationQuery();

        $query->query        = new Criterion\LogicalAnd( [
                                                             new Criterion\ContentTypeIdentifier( $this->contentClassIdentifier ),
                                                             new Criterion\LanguageCode( $languageCode, false ),
                                                         ] );
        $query->performCount = true;
        $query->limit        = $limit;
        $query->offset       = $offset;
        $query->sortClauses  = [ new ContentId() ];
        $results             = $this->searchService->findContent( $query );
        $items               = [];

        // Prepare an array with contents
        foreach( $results->searchHits as $item )
        {
            $items[] = $item->valueObject;
        }

        return $items;
    }
}
