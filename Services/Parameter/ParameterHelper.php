<?php

namespace SQLI\EzToolboxBundle\Services\Parameter;

class ParameterHelper
{
    /** @var ParameterHandlerRepository */
    private $parameterHandlerRepository;

    public function __construct( ParameterHandlerRepository $parameterHandlerRepository )
    {
        $this->parameterHandlerRepository = $parameterHandlerRepository;
    }

    public function isMaintenanceEnabled( int $locationId )
    {
        return $this->parameterHandlerRepository->getHandler( ParameterHandlerMaintenance::PARAMETER_NAME )->isEnabled( $locationId );
    }


    public function isBatchEnabled()
    {
        return $this->parameterHandlerRepository->getHandler( ParameterHandlerAbstractSimple::PARAMETER_NAME )->isEnabled() &&
               $this->parameterHandlerRepository->getHandler( ParameterHandlerBatchTransformation::PARAMETER_NAME )->isEnabled();
    }

    public function isMaxicoursEnabled()
    {
        return $this->parameterHandlerRepository->getHandler( ParameterHandlerAbstractEntity::PARAMETER_NAME )->isEnabled();
    }
}