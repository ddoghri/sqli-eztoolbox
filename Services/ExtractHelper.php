<?php
/**
 * Created by PhpStorm.
 * User: ccoupe
 * Date: 03/08/2017
 * Time: 10:14
 */

namespace SQLI\EzToolboxBundle\Services;

use Exception;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Repository;
use Netgen\TagsBundle\Core\Repository\TagsService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ExtractHelper
{
    /** @var TagsService */
    protected $tagService;
    /** @var Repository */
    protected $repository;

    public function __construct( Repository $repository, ?TagsService $tagService )
    {
        $this->tagService = $tagService;
        $this->repository = $repository;
    }

    /**
     * @param                 $aExportedIdentifiers
     * @param OutputInterface $output
     * @return array
     * @throws NotFoundException
     */
    public function createContentToExport( $aExportedIdentifiers, OutputInterface $output = null )
    {
        $content = [];
        $aContentType = [];

        $this->repository->setCurrentUser( $this->repository->getUserService()->loadUser( 14 ) );

        $contentTypeService = $this->repository->getContentTypeService();

        //TODO : bien generer les differents entrees quand on a plusieurs contentTypes
        if( is_null( $aExportedIdentifiers ) )
        {
            $aGroups = $contentTypeService->loadContentTypeGroups();
            foreach( $aGroups as $group )
            {
                $aCurrentContentTypes = $contentTypeService->loadContentTypes( $group );
                foreach( $aCurrentContentTypes as $currentContentType )
                {
                    $aExportedIdentifiers[] = $currentContentType->identifier;
                }
            }
        }
        if( !is_array( $aExportedIdentifiers ) )
        {
            $aExportedIdentifiers = array( $aExportedIdentifiers );
        }
        if( isset( $aExportedIdentifiers ) )
        {
            foreach( $aExportedIdentifiers as $exportedIdentifier )
            {
                if( is_numeric( $exportedIdentifier ) )
                {
                    $contentType = $contentTypeService->loadContentType( $exportedIdentifier );
                }
                else
                {
                    $contentType = $contentTypeService->loadContentTypeByIdentifier( $exportedIdentifier );
                }
                $contentTypeGroups = $contentType->getContentTypeGroups();
                //TODO : Voir si plus tard on peut faire autrement que prendre que le premier groupe
                // On ne retourne alors que le premeir groupe auquel est associé le contentType
                $contentTypeGroup = $contentTypeGroups[0];
                $groupIdentifier  = $contentTypeGroup->identifier;
                $identifier       = $contentType->identifier;
                $names            = $contentType->getNames();
                $descriptions     = $contentType->getDescriptions();
                $mainLanguageCode = $contentType->mainLanguageCode;
                $nameSchema       = $contentType->nameSchema;
                $fieldDefinitions = $contentType->getFieldDefinitions();
                $isContainer      = $contentType->isContainer;
                unset( $contentType );

                $aContentType['group_identifier'] = $groupIdentifier;
                $aContentType['identifier']       = $identifier;
                $aContentType['names']            = $names;
                $aContentType['descriptions']     = $descriptions;
                $aContentType['mainLanguageCode'] = $mainLanguageCode;
                $aContentType['nameSchema']       = $nameSchema;
                $aContentType['fieldDefinitions'] = $fieldDefinitions;
                $aContentType['isContainer']      = $isContainer;

                $content[$identifier] = $this->createYMLFile( $aContentType, $output );
            }
        }

        return $content;
    }

    /**
     * @param array<string>   $aContent
     * @param OutputInterface $output
     * @return string
     * @throws Exception
     */
    public function createYMLFile( $contentType, OutputInterface $output = null )
    {
        $content = "";
        //TODO : Mettre les champs à exporter dans un fichier de conf ?
        $content .= $this->extractInfosForYML( $contentType['identifier'], '', '', true );
        $content .= $this->extractInfosForYML( 'group_identifier', $contentType['group_identifier'], '    ' );
        $content .= $this->extractInfosForYML( 'identifier', $contentType['identifier'], '    ' );
        $content .= $this->extractInfosForYML( 'names', $contentType['names'], '    ' );
        $content .= $this->extractInfosForYML( 'descriptions', $contentType['descriptions'], '    ' );
        $content .= $this->extractInfosForYML( 'mainLanguageCode', $contentType['mainLanguageCode'], '    ' );
        $content .= $this->extractInfosForYML( 'nameSchema', $contentType['nameSchema'], '    ' );
        $content .= $this->extractInfosForYML( 'isContainer', $contentType['isContainer'], '    ' );
        $content .= "\r\n";
        $content .= "    ";
        $content .= "datatypes:";
        $content .= $this->extractDataTypesFromArrayToYML( $contentType['fieldDefinitions'], $output );
        //Ajout de ce saut de ligne pour autre contentTypes
        $content .= "\r\n";

        $contentTypeIdentifierForLog = $contentType['identifier'];
        if( !is_null( $output ) )
        {
            $output->writeln( "Extract du contentType d'identifier $contentTypeIdentifierForLog en ". $contentType['mainLanguageCode'] );
        }

        return $content;
    }

    /**
     * Fonction permettant d'extraire les infos d'un tableau ou string pour les replacer au bon
     * format dans un fichier yml
     * @param mixed   $entry
     * @param mixed   $infos
     * @param string  $spaces
     * @param boolean $firstElement
     * @param boolean $lastElement
     * @return string
     */
    public function extractInfosForYML( $entry, $infos, $spaces, $firstElement = false, $lastElement = false, $multipleArray = false )
    {
        $sInfos = '';

        if( is_array( $infos ) )
        {
            if( $firstElement == false )
            {
                $sInfos .= "\r\n";
            }
            $sInfos .= $spaces;
            $sInfos .= $entry;
            $sInfos .= ":";

            if( $multipleArray )
            {
                if( count( $infos ) )
                {
                    $dumpYaml = Yaml::dump( $infos, 1, 4 );
                    foreach( explode( "\n", $dumpYaml ) as $info )
                    {
                        if( $info != '' )
                        {
                            $sInfos .= "\r\n";
                            $sInfos .= $spaces . '    ';
                            $sInfos .= $info;
                        }
                    }
                }
            }
            else
            {
                foreach( $infos as $keyInfo => $info )
                {
                    $sInfos .= "\r\n";
                    $sInfos .= $spaces . '    ';
                    $sInfos .= $keyInfo;
                    $sInfos .= ': ';
                    $sInfos .= $info;
                }
            }
        }
        if( is_string( $infos ) || is_int( $infos ) || is_bool( $infos ) )
        {
            if( $firstElement == false )
            {
                $sInfos .= "\r\n";
            }
            $sInfos .= $spaces;
            $sInfos .= $entry;
            $sInfos .= ":";
            if( $infos != '' )
            {
                $sInfos .= " $infos";
            }
        }

        return $sInfos;
    }

    /**
     * @param array<\stdClass> $aFieldsDefinitions
     * @param OutputInterface  $output
     * @return string
     * @throws Exception
     */
    public function extractDataTypesFromArrayToYML( $aFieldsDefinitions, OutputInterface $output = null )
    {
        $sFieldsDefinitions = '';
        $spacesDataTypes    = '            ';
        foreach( $aFieldsDefinitions as $fieldsDefinition )
        {
            $sFieldsDefinitions .= "\r\n";
            $sFieldsDefinitions .= "        ";
            $sFieldsDefinitions .= "-";
            $sFieldsDefinitions .= $this->extractInfosForYML( 'identifier', $fieldsDefinition->identifier, $spacesDataTypes );
            $sFieldsDefinitions .= $this->extractInfosForYML( 'type', $fieldsDefinition->fieldTypeIdentifier, $spacesDataTypes );
            $sFieldsDefinitions .= $this->extractInfosForYML( 'names', $fieldsDefinition->names, $spacesDataTypes );
            $sFieldsDefinitions .= $this->extractInfosForYML( 'descriptions', $fieldsDefinition->descriptions, $spacesDataTypes );
            $sFieldsDefinitions .= $this->extractInfosForYML( 'field_group', $fieldsDefinition->fieldGroup, $spacesDataTypes );
            $sFieldsDefinitions .= $this->extractInfosForYML( 'position', $fieldsDefinition->position, $spacesDataTypes );
            $sFieldsDefinitions .= $this->extractInfosForYML( 'isTranslatable', $fieldsDefinition->isTranslatable, $spacesDataTypes );
            $sFieldsDefinitions .= $this->extractInfosForYML( 'isRequired', $fieldsDefinition->isRequired, $spacesDataTypes );
            $sFieldsDefinitions .= $this->extractInfosForYML( 'isSearchable', $fieldsDefinition->isSearchable, $spacesDataTypes );
            $sFieldsDefinitions .= $this->extractInfosForYML( 'isInfoCollector', $fieldsDefinition->isInfoCollector, $spacesDataTypes );
            $sFieldsDefinitions .= $this->extractInfosForYML( 'fieldSettings', $fieldsDefinition->fieldSettings, $spacesDataTypes, false, false, true );

            //Traitements spécifiques par type
            $fieldTypeIdentifier = $fieldsDefinition->fieldTypeIdentifier;
            switch( $fieldTypeIdentifier )
            {
                case 'ezstring':
                    $sFieldsDefinitions .= $this->extractInfosForYML( 'validatorConfiguration', $fieldsDefinition->validatorConfiguration['StringLengthValidator'], $spacesDataTypes );
                    break;
                case 'ezselection':
                    $sFieldsDefinitions .= $this->extractInfosForYML( 'isMultiple', $fieldsDefinition->fieldSettings['isMultiple'], $spacesDataTypes );
                    $options            = $fieldsDefinition->fieldSettings['options'];
                    $sFieldsDefinitions .= $this->extractInfosForYML( 'options', $options, $spacesDataTypes );
                    break;
                case 'eztags':
                    if( $this->tagService == null )
                    {
                        if( !is_null( $output ) )
                        {
                            $output->writeln( "netgen/tagsbundle is required to export eztags fields" );
                        }
                        break;
                    }
                    $hideRootTag        = $fieldsDefinition->fieldSettings['hideRootTag'];
                    $editView           = $fieldsDefinition->fieldSettings['editView'];
                    $tagsValueValidator = $fieldsDefinition->validatorConfiguration['TagsValueValidator'];
                    if( !isset( $tagsValueValidator['subTreeLimit'] ) )
                    {
                        if( !is_null( $output ) )
                        {
                            $output->writeln( "invalid eztags field data" );
                        }
                        break;
                    }
                    if( $tagsValueValidator['subTreeLimit'] == 0 )
                    {
                        break;
                    }
                    $tag                                = $this->tagService->loadTag( $tagsValueValidator['subTreeLimit'] );
                    $tagsValueValidator["subTreeLimit"] = $tag->keyword;
                    $sFieldsDefinitions                 .= $this->extractInfosForYML( 'hideRootTag', $hideRootTag, $spacesDataTypes );
                    $sFieldsDefinitions                 .= $this->extractInfosForYML( 'editView', $editView, $spacesDataTypes );
                    $sFieldsDefinitions                 .= $this->extractInfosForYML( 'TagsValueValidator', $tagsValueValidator, $spacesDataTypes );
                    break;
                default:
                    break;
            }
        }

        return $sFieldsDefinitions;
    }
}
