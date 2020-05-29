<?php

namespace SQLI\EzToolboxBundle\Command;

use SQLI\EzToolboxBundle\Services\ExtractHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractContentTypeCommand extends ContainerAwareCommand
{
    protected $log;
    /** @var ExtractHelper */
    protected $extractHelper;

    public function __construct( ExtractHelper $extractHelper )
    {
        $this->extractHelper = $extractHelper;

        parent::__construct('sqli:contentTypesInstaller:extract');
    }

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
        $output->writeln( "Debut de l'extract des content types" );

        $outputFilename        = $this->getContainer()->getParameter( 'kernel.root_dir' ) . '/../'.
                                 $input->getArgument( 'filename' );
        $identifierContentType = $input->getArgument( 'identifierContentType' );
        $content               = $this->extractHelper->createContentToExport( $identifierContentType, $output );

        //Ecriture du content type dans un fichier
        //TODO : Voir pour paramaettrer le nom et chemin du fichier de sortie
        //ouverture du fichier en mode écriture, création du fichier s'il n'existe pas.
        $fp = fopen( $outputFilename, "w" );
        fwrite( $fp, $content );
        fclose( $fp );

        $output->writeln( "Fin de l'extract des content types" );
    }
}
