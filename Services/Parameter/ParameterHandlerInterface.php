<?php

namespace SQLI\EzToolboxBundle\Services\Parameter;

use Symfony\Component\Console\Output\OutputInterface;

interface ParameterHandlerInterface
{
    public function listParameters();

    /**
     * @param                      $paramName
     * @param                      $paramValue
     * @param                      $contentIds
     * @param OutputInterface|null $output
     * @return array|true Array of error messages or true if no error
     */
    public function setParameter( $paramName, $paramValue, $contentIds, OutputInterface $output = null );

    public function showParameter( $paramName, $paramValue, OutputInterface $output = null );

    public function setData( $data, OutputInterface $output = null );

    public function getData( OutputInterface $output = null );

    public function showData( OutputInterface $output = null );

    public function isEnabled( $params = null );
}