<?php
/**
 * File containing the CreateContentTypeCommand class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace SQLI\EzToolboxBundle\Command;

use eZ\Publish\API\Repository\Values\ContentType\FieldDefinition;
use eZ\Publish\Core\Persistence\Legacy\Exception\TypeNotFound;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

class CreateOrUpdateContentTypeCommand extends ContainerAwareCommand
{
    protected $log;

    protected function configure()
    {
        //Prevision du cas du nom d'un fichier si on decide de traiter fichier par fichier ou si on traite tout d'un coup
        $this
            ->setName( 'sqli:contentTypesInstaller:create_or_update' )
            ->setDescription( "Create or update ContentTypes from yaml files" )
            ->setDefinition(
                array(
                    new InputArgument(
                        'filename',
                        InputArgument::OPTIONAL,
                        'name of the file which describe the class'
                    )
                )
            );
    }

    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $styleStart = new OutputFormatterStyle( 'blue' );
        $output->getFormatter()->setStyle( 'start', $styleStart );
        $styleStop = new OutputFormatterStyle( 'cyan' );
        $output->getFormatter()->setStyle( 'stop', $styleStop );

        $today     = date( "Y-m-d" );
        $this->log = new Logger( 'CreateOrUpdateContentTypesCommandLogger' );
        $this->log->pushHandler( new StreamHandler( $this->getContainer()->get( 'kernel' )->getLogDir() . '/create-update_content_types-' . $today . '.log' ) );

        //TODO : Manage Errors and exceptions
        //TODO : Gerer l'update et suite du create
        //TODO :  diviser en fonction

        $installationDirectory = $this->getContainer()->getParameter( 'sqli_ez_toolbox.contenttype_installer.installation_directory' );
        $isAbsolutePath        = $this->getContainer()->getParameter( 'sqli_ez_toolbox.contenttype_installer.is_absolute_path' );

        if( !$isAbsolutePath )
        {
            $installationDirectory = $this->getContainer()->getParameter( 'kernel.root_dir' ) . '/../' . $installationDirectory;
        }

        $aFiles = $this->getDirContents( $installationDirectory );

        $fileName = $input->getArgument( 'filename' );

        if( isset( $fileName ) && $fileName != null )
        {
            $aFiles = array( "$installationDirectory/$fileName" );
        }

        foreach( $aFiles as $file )
        {
            $yaml        = new Parser();
            $aYmlContent = [];
            try
            {
                if( isset( $fileName ) && $fileName != null )
                {
                    $aYmlContent = $yaml->parse( file_get_contents( $file ) );
                    if( isset( $aYmlContent['imports'] ) )
                    {
                        foreach( $aYmlContent['imports'] as $import )
                        {
                            $extraContent  = $yaml->parse( file_get_contents( $import['resource'] ) );
                            $aYmlContent[] = $extraContent;
                        }
                        unset( $aYmlContent['imports'] );
                        $atempYMLFile = $aYmlContent;
                        $aYmlContent  = array();
                        //TODO voir pour reorganiser
                        foreach( $atempYMLFile as $uniqueYML )
                        {
                            foreach( $uniqueYML as $uniqueContentTYpe )
                            {
                                $aYmlContent[array_keys( $uniqueYML )[0]] = $uniqueContentTYpe;
                            }
                        }
                    }
                }
                else
                {
                    $aYmlContent = $yaml->parse(
                        file_get_contents( $file )
                    );
                }
            }
            catch( ParseException $e )
            {
                $errorMsg = $e->getMessage();
                printf( "Unable to parse the YAML string (file: %s): %s", $file, $errorMsg );
                $this->log->addError( "Unable to parse the YAML string (file: $file): $errorMsg" );
            }

            /** @var $repository \eZ\Publish\API\Repository\Repository */
            $repository         = $this->getContainer()->get( 'ezpublish.api.repository' );
            $contentTypeService = $repository->getContentTypeService();
            $fieldTypeService   = $repository->getFieldTypeService();
            $user = $repository->getUserService()->loadUserByLogin('admin');
            $repository->getPermissionResolver()->setCurrentUserReference($user);

            //Mise a jour de tous les user modifier à 14 pour eviter les problèmes de brouillons
            try
            {
                $doctrine = $this->getContainer()->get( 'doctrine' );
                $em       = $doctrine->getEntityManager();
                $sql      = 'UPDATE ezcontentclass SET modifier_id = 14';
                $stmt     = $em->getConnection()->prepare( $sql );
                $stmt->execute();
            }
            catch( \Exception $doctrineException )
            {
                $message = '<info>La mise à jour du modifieID a echoué</info>';
                $output->writeln( $message );
            }

            foreach( $aYmlContent as $identifierContentTypeToExport => $contentTypeToExport )
            {
                $rootContentType       = $aYmlContent[$identifierContentTypeToExport];
                $groupIdentifier       = $rootContentType['group_identifier'];
                $contentTypeIdentifier = $rootContentType['identifier'];

                //Update
                try
                {
                    $output->writeln( "<start>on va faire un update sur " . $contentTypeIdentifier . "</start>" );

                    $contentTypeLoaded = $contentTypeService->loadContentTypeByIdentifier(
                        $contentTypeIdentifier
                    );

                    $this->log->addInfo( "Début de l'update pour le content type d'identifier $contentTypeIdentifier" );
                    try
                    {
                        try
                        {
                            try
                            {
                                //On assign le nouveau groupe
                                $newGroupIdentifier = $contentTypeService->loadContentTypeGroupByIdentifier( $groupIdentifier );
                                $contentTypeService->assignContentTypeGroup( $contentTypeLoaded, $newGroupIdentifier );
                            }
                            catch( \Exception $e )
                            {
                                $message = $e->getMessage();
                                if( $e->getCode() == 0 )
                                {
                                    $message = '<info>Le "group_identifier" est identique aucune mise a jour a été effectué</info>';
                                }
                                elseif( $e->getCode() == 404 )
                                {
                                    $message = '<info>Le "group_identifier" que vous voulez ajouté n\'existe pas</info>';
                                }
                                $output->writeln( $message );
                                $this->log->addError( $message );
                            }

                            //On unassign les anciens groupe
                            foreach( $contentTypeLoaded->contentTypeGroups as $groups )
                            {
                                if( $groups->identifier != $groupIdentifier )
                                {
                                    //On enleve l'ancien groupe
                                    $contentTypeService->unassignContentTypeGroup( $contentTypeLoaded, $groups );
                                }
                            }
                        }
                        catch( \Exception $e )
                        {
                            $output->writeln( $e->getMessage() );
                            $this->log->addError( $e->getMessage() );
                        }

                        //On essaie de creer un draft. Si un draft existe deja on le charge
                        try
                        {
                            $contentTypeDraft = $contentTypeService->createContentTypeDraft(
                                $contentTypeLoaded
                            );
                        }
                        catch( \Exception $exceptionNotDraft )
                        {
                            $contentTypeDraft = $contentTypeService->loadContentTypeDraft(
                                $contentTypeLoaded->id
                            );
                        }

                        $typeUpdate = $contentTypeService->newContentTypeUpdateStruct();
                        //Update du content type en lui meme
                        $typeUpdate->identifier             = $contentTypeIdentifier;
                        $typeUpdate->remoteId               = $contentTypeLoaded->remoteId;
                        $typeUpdate->urlAliasSchema         = $contentTypeLoaded->urlAliasSchema;
                        $typeUpdate->nameSchema             = isset( $rootContentType['nameSchema'] ) ? $rootContentType['nameSchema'] : $contentTypeLoaded->nameSchema;
                        $typeUpdate->isContainer            = isset( $rootContentType['isContainer'] ) ? $rootContentType['isContainer'] : $contentTypeLoaded->isContainer;
                        $typeUpdate->mainLanguageCode       = isset( $rootContentType['mainLanguageCode'] ) ? $rootContentType['mainLanguageCode'] : $contentTypeLoaded->mainLanguageCode;
                        $typeUpdate->defaultAlwaysAvailable = $contentTypeLoaded->defaultAlwaysAvailable;
                        $typeUpdate->modifierId             = 14;
                        $typeUpdate->modificationDate       = $this->createDateTime();
                        $typeUpdate->names                  = isset( $rootContentType['names'] ) ? $rootContentType['names'] : $contentTypeLoaded->getNames();
                        $typeUpdate->descriptions           = isset( $rootContentType['descriptions'] ) ? $rootContentType['descriptions'] : $contentTypeLoaded->getDescriptions();

                        //Update des fields
                        $aDataTypes = $rootContentType['datatypes'];

                        //TODO : Gerer le exceptions
                        //TODO : Comme pour le create, dyanmiser les champs à updater

                        $aIdentifiersInFile = [];
                        foreach( $aDataTypes as $aDataType )
                        {
                            try
                            {
                                $fieldIdentifier = $aDataType['identifier'];

                                $aIdentifiersInFile[]  = $fieldIdentifier;
                                $fieldType             = $aDataType['type'];
                                $isFieldTypeSearchable = $fieldTypeService->getFieldType( $fieldType )->isSearchable();
                                $fieldNames            = array_key_exists(
                                    'names',
                                    $aDataType
                                ) ? $aDataType['names'] : null;
                                $fieldDescriptions     = array_key_exists(
                                    'descriptions',
                                    $aDataType
                                ) ? $aDataType['descriptions'] : null;
                                $fieldGroup            = array_key_exists(
                                    'field_group',
                                    $aDataType
                                ) ? $aDataType['field_group'] : null;
                                $fieldPosition         = array_key_exists(
                                    'position',
                                    $aDataType
                                ) ? $aDataType['position'] : null;
                                $fieldTranslatable     = array_key_exists(
                                    'isTranslatable',
                                    $aDataType
                                ) ? $aDataType['isTranslatable'] : null;
                                $fieldRequired         = array_key_exists(
                                    'isRequired',
                                    $aDataType
                                ) ? $aDataType['isRequired'] : null;
                                $fieldSearchable       = array_key_exists(
                                    'isSearchable',
                                    $aDataType
                                ) ? $aDataType['isSearchable'] : null;
                                $fieldInfoCollector    = array_key_exists(
                                    'isInfoCollector',
                                    $aDataType
                                ) ? $aDataType['isInfoCollector'] : null;
                            }
                            catch( \Exception $a )
                            {
                                $output->writeln( '<error>' . $e->getMessage() . '<error>' );
                                continue;
                            }

                            $fieldDefinition = $contentTypeLoaded->getFieldDefinition(
                                $fieldIdentifier
                            );

                            if( $fieldDefinition )
                            {
                                try
                                {
                                    $fieldUpdateStruct                 = $contentTypeService->newFieldDefinitionUpdateStruct(
                                        $fieldIdentifier,
                                        $fieldType
                                    );
                                    $fieldUpdateStruct->names          = $fieldNames;
                                    $fieldUpdateStruct->descriptions   = $fieldDescriptions;
                                    $fieldUpdateStruct->fieldGroup     = $fieldGroup;
                                    $fieldUpdateStruct->position       = $fieldPosition;
                                    $fieldUpdateStruct->isTranslatable = (boolean)$fieldTranslatable;
                                    $fieldUpdateStruct->isRequired     = (boolean)$fieldRequired;
                                    if( $isFieldTypeSearchable )
                                    {
                                        $fieldUpdateStruct->isSearchable = (boolean)$fieldSearchable;
                                    }
                                    $fieldUpdateStruct->isInfoCollector = (boolean)$fieldInfoCollector;
                                    if( array_key_exists( 'new_identifier', $aDataType ) && ( $aDataType['new_identifier'] != $fieldDefinition->identifier ) )
                                    {
                                        $fieldUpdateStruct->identifier = $aDataType['new_identifier'];
                                    }

                                    //Traitements spécifiques par type
                                    $fieldTypeIdentifier = $fieldDefinition->fieldTypeIdentifier;
                                    switch( $fieldTypeIdentifier )
                                    {
                                        case 'ezobjectrelationlist':
                                        case 'ezobjectrelation':
                                            if( array_key_exists( 'fieldSettings', $aDataType ) )
                                            {
                                                $fieldUpdateStruct->fieldSettings = $aDataType['fieldSettings'];
                                            }
                                            break;
                                        case 'ezstring':
                                            $stringLengthValidator                     = array_key_exists(
                                                'validatorConfiguration',
                                                $aDataType
                                            ) ? $aDataType['validatorConfiguration'] : [];
                                            $fieldUpdateStruct->validatorConfiguration = [
                                                'StringLengthValidator' => $stringLengthValidator
                                            ];
                                            break;
                                        case 'ezselection':
                                            $isMultipleField                  = array_key_exists(
                                                'isMultiple',
                                                $aDataType
                                            ) ? $aDataType['isMultiple'] : null;
                                            $fieldSettings                    = $fieldDefinition->getFieldSettings();
                                            $selectionOptions                 = array_key_exists(
                                                'options',
                                                $aDataType
                                            ) ? $aDataType['options'] : array();
                                            $fieldUpdateStruct->fieldSettings = array( 'isMultiple' => (bool)$isMultipleField, 'options' => $selectionOptions );
                                            break;
                                        case 'eztags':
                                            $hideRootTag        = array_key_exists(
                                                'hideRootTag',
                                                $aDataType
                                            ) ? $aDataType['hideRootTag'] : null;
                                            $editView           = array_key_exists(
                                                'editView',
                                                $aDataType
                                            ) ? $aDataType['editView'] : 'Default';
                                            $tagsValueValidator = array_key_exists(
                                                'TagsValueValidator',
                                                $aDataType
                                            ) ? $aDataType['TagsValueValidator'] : array();

                                            //TODO : renforcer les controles sur l'existence et le traitements des tags by keywords
                                            if( $this->getContainer()->get( 'ezpublish.api.service.tags' ) )
                                            {
                                                $tagsService = $this->getContainer()->get( 'ezpublish.api.service.tags' );
                                            }
                                            else
                                            {
                                                break;
                                            }
                                            $aFieldsNamesKeys = array_keys( $fieldNames );
                                            $tag              = $tagsService->loadTagsByKeyword( $tagsValueValidator['subTreeLimit'], $aFieldsNamesKeys[0] );
                                            if( count( $tag ) )
                                            {
                                                $tagsValueValidator["subTreeLimit"]        = $tag[0]->id;
                                                $fieldUpdateStruct->validatorConfiguration = [ 'TagsValueValidator' => $tagsValueValidator ];
                                            }
                                            else
                                            {
                                                $output->writeln( '<error>Pour le type "' . $fieldTypeIdentifier . '" le tag (' . $tagsValueValidator['subTreeLimit'] . ') n\'est pas valable (identifier: ' . $fieldIdentifier . ')<error>' );
                                            }

                                            $fieldUpdateStruct->fieldSettings = array( 'hideRootTag' => (bool)$hideRootTag, 'editView' => $editView );
                                            break;
                                        default:
                                            break;
                                    }

                                    $contentTypeService->updateFieldDefinition(
                                        $contentTypeDraft,
                                        $contentTypeDraft->getFieldDefinition( $fieldIdentifier ),
                                        $fieldUpdateStruct
                                    );
                                }
                                catch( \Exception $e )
                                {
                                    $output->writeln( '<error>' . $e->getMessage() . ' (identifier: ' . $fieldIdentifier . ')<error>' );
                                    $this->log->addError( $e->getMessage() );
                                }

                                $this->log->addInfo( "Le field  d'identifier $fieldIdentifier a été updaté " );
                            }
                            else
                            {
                                $fieldCreateStruct                 = $contentTypeService->newFieldDefinitionCreateStruct(
                                    $fieldIdentifier,
                                    $fieldType
                                );
                                $fieldCreateStruct->names          = $fieldNames;
                                $fieldCreateStruct->descriptions   = $fieldDescriptions;
                                $fieldCreateStruct->fieldGroup     = $fieldGroup;
                                $fieldCreateStruct->position       = $fieldPosition;
                                $fieldCreateStruct->isTranslatable = (boolean)$fieldTranslatable;
                                $fieldCreateStruct->isRequired     = (boolean)$fieldRequired;
                                if( $isFieldTypeSearchable )
                                {
                                    $fieldCreateStruct->isSearchable = (boolean)$fieldSearchable;
                                }
                                $fieldCreateStruct->isInfoCollector = (boolean)$fieldInfoCollector;

                                //Traitements spécifiques par type
                                switch( $fieldType )
                                {
                                    case 'ezobjectrelationlist':
                                    case 'ezobjectrelation':
                                        if( array_key_exists( 'fieldSettings', $aDataType ) )
                                        {
                                            $fieldCreateStruct->fieldSettings = $aDataType['fieldSettings'];
                                        }
                                        break;
                                    case 'ezstring':
                                        $stringLengthValidator                     = array_key_exists(
                                            'validatorConfiguration',
                                            $aDataType
                                        ) ? $aDataType['validatorConfiguration'] : [];
                                        $fieldCreateStruct->validatorConfiguration = [
                                            'StringLengthValidator' => $stringLengthValidator
                                        ];
                                        break;
                                    case 'ezselection':
                                        $isMultipleField                  = array_key_exists(
                                            'isMultiple',
                                            $aDataType
                                        ) ? $aDataType['isMultiple'] : null;
                                        $selectionOptions                 = array_key_exists(
                                            'options',
                                            $aDataType
                                        ) ? $aDataType['options'] : array();
                                        $fieldCreateStruct->fieldSettings = array( 'isMultiple' => (bool)$isMultipleField, 'options' => $selectionOptions );
                                        break;
                                    case 'eztags':
                                        $hideRootTag = array_key_exists(
                                            'hideRootTag',
                                            $aDataType
                                        ) ? $aDataType['hideRootTag'] : null;
                                        $editView    = array_key_exists(
                                            'editView',
                                            $aDataType
                                        ) ? $aDataType['editView'] : null;

                                        $tagsValueValidator = array_key_exists(
                                            'TagsValueValidator',
                                            $aDataType
                                        ) ? $aDataType['TagsValueValidator'] : array();

                                        //TODO : renforcer les controles sur l'existence et le traitements des tags by keywords
                                        if( $this->getContainer()->get( 'ezpublish.api.service.tags' ) )
                                        {
                                            $tagsService = $this->getContainer()->get( 'ezpublish.api.service.tags' );
                                        }
                                        else
                                        {
                                            break;
                                        }
                                        $aFieldsNamesKeys = array_keys( $fieldNames );
                                        $tag              = $tagsService->loadTagsByKeyword( $tagsValueValidator['subTreeLimit'], $aFieldsNamesKeys[0] );
                                        if( count( $tag ) )
                                        {
                                            $tagsValueValidator["subTreeLimit"]        = $tag[0]->id;
                                            $fieldCreateStruct->validatorConfiguration = [ 'TagsValueValidator' => $tagsValueValidator ];
                                        }
                                        else
                                        {
                                            $output->writeln( '<error>Pour le type "' . $fieldType . '" le tag (' . $tagsValueValidator['subTreeLimit'] . ') n\'est pas valable (identifier: ' . $fieldIdentifier . ')<error>' );
                                        }

                                        $fieldCreateStruct->fieldSettings = array( 'hideRootTag' => (bool)$hideRootTag, 'editView' => $editView );
                                        break;
                                    default:
                                        break;
                                }

                                //TODO : Gerer les propietes du nouveau champ ajouté comme lors de
                                //creation d'un champ nouveau champ pour un content type existant
                                try
                                {
                                    $contentTypeService->addFieldDefinition(
                                        $contentTypeDraft,
                                        $fieldCreateStruct
                                    );
                                }
                                catch( \Exception $exception )
                                {
                                    $this->log->addError( "Le field  d'identifier $fieldIdentifier existe déja " );
                                }

                                $this->log->addInfo( "Le field  d'identifier $fieldIdentifier a été ajouté " );
                            }
                        }

                        //Suppression des field à supprimer dans le fichier
                        $aExistingFields            = $contentTypeLoaded->getFieldDefinitions();
                        $aIdentifiersExistingFields = array();
                        foreach( $aExistingFields as $existingField )
                        {
                            $aIdentifiersExistingFields[] = $existingField->identifier;
                        }
                        $aFieldsToDelete = array_diff(
                            $aIdentifiersExistingFields,
                            $aIdentifiersInFile
                        );
                        foreach( $aFieldsToDelete as $fieldsToDelete )
                        {
                            if( !( $contentTypeDraft->getFieldDefinition( $fieldsToDelete ) instanceof FieldDefinition ) )
                            {
                                continue;
                            }

                            $contentTypeService->removeFieldDefinition(
                                $contentTypeDraft,
                                $contentTypeDraft->getFieldDefinition( $fieldsToDelete )
                            );

                            $this->log->addInfo( "Le field  d'identifier $fieldIdentifier a été supprimé " );
                        }

                        //Update and publish draft
                        $contentTypeService->updateContentTypeDraft( $contentTypeDraft, $typeUpdate );
                        $contentTypeService->publishContentTypeDraft( $contentTypeDraft );
                    }
                    catch( \eZ\Publish\API\Repository\Exceptions\NotFoundException $e )
                    {
                        $output->writeln( $e->getMessage() );
                        $this->log->addError( $e->getMessage() );
                    }
                    catch( \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException $e )
                    {
                        $output->writeln( $e->getMessage() );
                        $this->log->addError( $e->getMessage() );
                    }
                    catch( \eZ\Publish\API\Repository\Exceptions\ContentValidationException $e )
                    {
                        $output->writeln( $e->getMessage() );
                        $this->log->addError( $e->getMessage() );
                    }

                    $output->writeln( "<stop>Le content type d'identifier  $contentTypeIdentifier a été updaté\n</stop>" );
                    $this->log->addInfo( "Le content type d'identifier  $contentTypeIdentifier a été updaté" );
                }
                catch( TypeNotFound $e ) //Create
                {
                    try
                    {
                        $contentTypeGroup = $contentTypeService->loadContentTypeGroupByIdentifier(
                            $groupIdentifier
                        );
                    }
                    catch( \eZ\Publish\API\Repository\Exceptions\NotFoundException $e )
                    {
                        $output->writeln(
                            "content type group with identifier $groupIdentifier not found"
                        );
                        $this->log->addError( "content type group with identifier $groupIdentifier not found" );
                        $contentTypeStruct = $contentTypeService->newContentTypeGroupCreateStruct( $groupIdentifier );
                        $contentTypeGroup  = $contentTypeService->createContentTypeGroup( $contentTypeStruct );
                    }

                    // instantiate a ContentTypeCreateStruct with the given content type identifier and set parameters
                    $contentTypeCreateStruct = $contentTypeService->newContentTypeCreateStruct(
                        $contentTypeIdentifier
                    );

                    $this->log->addInfo( "Début de la création du content type d'identifier $contentTypeIdentifier " );

                    $mainLanguageCode                          = $rootContentType['mainLanguageCode'];
                    $contentTypeCreateStruct->mainLanguageCode = $mainLanguageCode;

                    // We set the Content Type naming pattern
                    $nameSchema                          = $rootContentType['nameSchema'];
                    $contentTypeCreateStruct->nameSchema = $nameSchema;

                    // set names for the content type
                    $aNames       = [];
                    $aConfigNames = $rootContentType['names'];
                    if( isset( $aConfigNames ) )
                    {
                        foreach( $aConfigNames as $language => $name )
                        {
                            $aNames[$language] = $name;
                        }
                    }

                    $contentTypeCreateStruct->names = $aNames;

                    // set description for the content type
                    $aDescriptions = [];

                    $aConfigDescriptions = $rootContentType['descriptions'];

                    if( isset( $aConfigDescriptions ) )
                    {
                        foreach( $aConfigDescriptions as $language => $description )
                        {
                            $aDescriptions[$language] = $description;
                        }
                    }

                    $contentTypeCreateStruct->descriptions = $aDescriptions;

                    //TODO: traiter dans boucle pour les attributs et dynamiser
                    //TODO: tester les field definitions et les implementer pour ceux n'existant aps
                    //TODO: Voir comment refactorer tout cela dans fonctions
                    $aDataTypes = $rootContentType['datatypes'];

                    try
                    {
                        foreach( $aDataTypes as $aDataType )
                        {
                            try
                            {
                                $fieldIdentifier       = $aDataType['identifier'];
                                $fieldType             = $aDataType['type'];
                                $isFieldTypeSearchable = $fieldTypeService->getFieldType( $fieldType )->isSearchable();
                                $fieldCreateStruct     = $contentTypeService->newFieldDefinitionCreateStruct(
                                    $fieldIdentifier,
                                    $fieldType
                                );

                                $aFieldNames       = [];
                                $aFieldConfigNames = array_key_exists(
                                    'names',
                                    $aDataType
                                ) ? $aDataType['names'] : null;

                                if( isset( $aFieldConfigNames ) )
                                {
                                    foreach( $aFieldConfigNames as $language => $fieldName )
                                    {
                                        $aFieldNames[$language] = $fieldName;
                                    }
                                }

                                $fieldCreateStruct->names = $aFieldNames;

                                $aFieldDescriptions       = [];
                                $aFieldConfigDescriptions = array_key_exists(
                                    'descriptions',
                                    $aDataType
                                ) ? $aDataType['descriptions'] : null;

                                if( isset( $aFieldConfigDescriptions ) )
                                {
                                    foreach( $aFieldConfigDescriptions as $language => $fieldDescription )
                                    {
                                        $aFieldDescriptions[$language] = $fieldDescription;
                                    }
                                }

                                $fieldCreateStruct->descriptions = $aFieldDescriptions;

                                $fieldGroup                    = $aDataType['field_group'];
                                $fieldCreateStruct->fieldGroup = $fieldGroup;

                                $fieldPosition               = $aDataType['position'];
                                $fieldCreateStruct->position = $fieldPosition;

                                $fieldTranslatable                 = $aDataType['isTranslatable'];
                                $fieldCreateStruct->isTranslatable = (boolean)$fieldTranslatable;

                                $fieldRequired                 = $aDataType['isRequired'];
                                $fieldCreateStruct->isRequired = (boolean)$fieldRequired;

                                $fieldSearchable = $aDataType['isSearchable'];
                                if( $isFieldTypeSearchable )
                                {
                                    $fieldCreateStruct->isSearchable = (boolean)$fieldSearchable;
                                }

                                $fieldInfoCollector                 = isset( $aDataType['isInfoCollector'] ) ? $aDataType['isInfoCollector'] : '';
                                $fieldCreateStruct->isInfoCollector = (boolean)$fieldInfoCollector;
                            }
                            catch( \Exception $a )
                            {
                                $output->writeln( '<error>' . $e->getMessage() . '<error>' );
                                continue;
                            }

                            //Traitements spécifiques par type
                            switch( $fieldType )
                            {
                                case 'ezobjectrelationlist':
                                case 'ezobjectrelation':
                                    if( array_key_exists( 'fieldSettings', $aDataType ) )
                                    {
                                        $fieldCreateStruct->fieldSettings = $aDataType['fieldSettings'];
                                    }
                                    break;
                                case 'ezstring':
                                    $stringLengthValidator                     = array_key_exists(
                                        'validatorConfiguration',
                                        $aDataType
                                    ) ? $aDataType['validatorConfiguration'] : [];
                                    $fieldCreateStruct->validatorConfiguration = [
                                        'StringLengthValidator' => $stringLengthValidator
                                    ];
                                    break;
                                case 'ezselection':
                                    $isMultipleField                  = array_key_exists(
                                        'isMultiple',
                                        $aDataType
                                    ) ? $aDataType['isMultiple'] : null;
                                    $selectionOptions                 = array_key_exists(
                                        'options',
                                        $aDataType
                                    ) ? $aDataType['options'] : array();
                                    $fieldCreateStruct->fieldSettings = array( 'isMultiple' => (bool)$isMultipleField, 'options' => $selectionOptions );
                                    break;
                                case 'eztags':
                                    $hideRootTag        = array_key_exists(
                                        'hideRootTag',
                                        $aDataType
                                    ) ? $aDataType['hideRootTag'] : null;
                                    $editView           = array_key_exists(
                                        'editView',
                                        $aDataType
                                    ) ? $aDataType['editView'] : null;
                                    $tagsValueValidator = array_key_exists(
                                        'TagsValueValidator',
                                        $aDataType
                                    ) ? $aDataType['TagsValueValidator'] : array();
                                    //TODO : renforcer les controles sur l'existence et le traitements des tags by keywords
                                    if( $this->getContainer()->get( 'ezpublish.api.service.tags' ) )
                                    {
                                        $tagsService = $this->getContainer()->get( 'ezpublish.api.service.tags' );
                                    }
                                    else
                                    {
                                        break;
                                    }
                                    $aFieldsNamesKeys = array_keys( $fieldNames );
                                    $tag              = $tagsService->loadTagsByKeyword( $tagsValueValidator['subTreeLimit'], $aFieldsNamesKeys[0] );
                                    if( count( $tag ) )
                                    {
                                        $tagsValueValidator["subTreeLimit"]        = $tag[0]->id;
                                        $fieldCreateStruct->validatorConfiguration = [ 'TagsValueValidator' => $tagsValueValidator ];
                                    }
                                    else
                                    {
                                        $output->writeln( '<error>Pour le type "' . $fieldType . '" le tag (' . $tagsValueValidator['subTreeLimit'] . ') n\'est pas valable (identifier: ' . $fieldIdentifier . ')<error>' );
                                    }

                                    $fieldCreateStruct->fieldSettings = array( 'hideRootTag' => (bool)$hideRootTag, 'editView' => $editView );
                                    break;
                                default:
                                    break;
                            }

                            $contentTypeCreateStruct->addFieldDefinition( $fieldCreateStruct );
                            $this->log->addInfo( "Le field  d'identifier $fieldIdentifier a été crée " );
                        }
                    }
                    catch( \Exception $exception )
                    {
                        $this->log->addError( $exception->getMessage() . ' file : ' . __FILE__ . ' line : ' . __LINE__ );
                        continue;
                    }

                    try
                    {
                        $contentTypeDraft = $contentTypeService->createContentType(
                            $contentTypeCreateStruct,
                            array( $contentTypeGroup )
                        );
                        $contentTypeService->publishContentTypeDraft( $contentTypeDraft );
                        $output->writeln(
                            "<info>Content type created '$contentTypeIdentifier' with ID $contentTypeDraft->id"
                        );
                        $this->log->addInfo( "Le content type d'identifiant $contentTypeDraft->identifier a été crée avec l'id $contentTypeDraft->id" );
                    }
                    catch( \eZ\Publish\API\Repository\Exceptions\UnauthorizedException $e )
                    {
                        $output->writeln( "<error>" . $e->getMessage() . "</error>" );
                        $this->log->addError( "<error>" . $e->getMessage() . "</error>" );
                    }
                    catch( \eZ\Publish\API\Repository\Exceptions\ForbiddenException $e )
                    {
                        $output->writeln( "<error>" . $e->getMessage() . "</error>" );
                        $this->log->addError( "<error>" . $e->getMessage() . "</error>" );
                    }
                    catch( \eZ\Publish\Core\Base\Exceptions\ContentTypeFieldDefinitionValidationException $e )
                    {
                        $output->writeln( "<error>" . $e->getMessage() . "</error>" );
                        $this->log->addError( "<error>" . $e->getMessage() . "</error>" );
                    }
                }
            }
        }
    }

    private function getDirContents( $dir, &$results = array() )
    {
        $files = scandir( $dir );
        foreach( $files as $key => $value )
        {
            $path = realpath( $dir . DIRECTORY_SEPARATOR . $value );
            if( !is_dir( $path ) )
            {
                $results[] = $path;
            }
            else
            {
                if( $value != "." && $value != ".." )
                {
                    $this->getDirContents( $path, $results );
                }
            }
        }

        return $results;
    }

    /**
     * Only for internal use.
     *
     * Creates a \DateTime object for $timestamp in the current time zone
     *
     * @param int $timestamp
     *
     * @return \DateTime
     */
    private function createDateTime( $timestamp = null )
    {
        $dateTime = new \DateTime();
        if( $timestamp !== null )
        {
            $dateTime->setTimestamp( $timestamp );
        }

        return $dateTime;
    }
}
