<?php

namespace SQLI\EzToolboxBundle\Command;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SQLI\EzToolboxBundle\Services\ExtractHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractContentTypeCommand extends ContainerAwareCommand
{
    protected $log;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName( 'sqli:contentTypesInstaller:extract' )
            ->setDescription( 'Extract Content Types in yml' )
            ->setDefinition(
                array(
                    new InputArgument(
                        'filename',
                        InputArgument::REQUIRED,
                        'output filename'
                    ),
                    new InputArgument(
                        'identifierContentType',
                        InputArgument::OPTIONAL,
                        'identifier contentType to Extract'
                    )
                )
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $today     = date( "Y-m-d" );
        $this->log = new Logger( 'ExtractContentTypesCommandLogger' );
        $this->log->pushHandler( new StreamHandler( $this->getContainer()->get( 'kernel' )->getLogDir() . '/extract_content_types-' . $today . '.log' ) );

        $this->log->addInfo( "Debut de l'extract des content types" );

        $outputFilename        = $input->getArgument( 'filename' );
        $identifierContentType = $input->getArgument( 'identifierContentType' );
        $content               = $this->getContainer()
            ->get( ExtractHelper::class )
            ->createContentToExport( $identifierContentType, $this->log );

        //Ecriture du content type dans un fichier
        //TODO : Voir pour paramaettrer le nom et chemin du fichier de sortie
        //ouverture du fichier en mode écriture, création du fichier s'il n'existe pas.
        $fp = fopen( $outputFilename, "w" );
        fwrite( $fp, $content );
        fclose( $fp );

        $this->log->addInfo( "Fin de l'extract des content types" );
    }
}
