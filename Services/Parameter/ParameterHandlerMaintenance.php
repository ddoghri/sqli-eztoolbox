<?php

namespace SQLI\EzToolboxBundle\Services\Parameter;

use SQLI\EzToolboxBundle\Entity\Doctrine\Parameter;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use Symfony\Component\Console\Output\OutputInterface;

class ParameterHandlerMaintenance extends ParameterHandlerAbstractObjectState implements ParameterHandlerInterface
{
    const PARAMETER_NAME     = "maintenance";
    const PARAMETER_ENABLED  = "enabled";
    const PARAMETER_DISABLED = "disabled";

    /**
     * LocationID is under maintenance ?
     *
     * @param int $params
     * @return bool
     */
    public function isEnabled( $params = null )
    {
        try
        {
            if( is_numeric( $params ) )
            {
                $location = $this->repository->getLocationService()->loadLocation( $params );

                // Get state object maintenance
                $groupState = $this->stateHandler->loadGroupByIdentifier( self::PARAMETER_NAME );
                $state      = $this->stateHandler->loadByIdentifier( self::PARAMETER_ENABLED, $groupState->id );

                // Search if an ancestor is in maintenance state
                $params =
                    [
                        new Criterion\Ancestor( $location->pathString ),
                        new Criterion\ObjectStateId( $state->id )
                    ];

                // If at least one content is in maintenance, return true
                return ( $this->count( $params ) != 0 );
            }
        }
        catch( \Exception $exception )
        {
            return false;
        }

        return false;
    }

    public function setParameter( $paramName, $paramValue, $contentIds, OutputInterface $output = null )
    {
        if( is_null( $contentIds ) )
        {
            // Search in data
            $contentIds = implode( ',', $this->getData( $output ) );
        }

        // Update param's value in entity
        if( $parameter = $this->entityManager
            ->getRepository( Parameter::class )
            ->findOneByName( self::PARAMETER_NAME ) )
        {
            /** @var Parameter */
            $parameter->setValue( $paramValue );

            $this->entityManager->persist( $parameter );
            $this->entityManager->flush();
        }

        return parent::setParameter( $paramName, $paramValue, $contentIds, $output );
    }

    /**
     * @param OutputInterface|null $output
     * @return array|null
     */
    public function getData( OutputInterface $output = null )
    {
        if( $parameter = $this->entityManager
            ->getRepository( Parameter::class )
            ->findOneByName( self::PARAMETER_NAME ) )
        {
            return $parameter->getParams();
        }

        return null;
    }

    public function setData( $data, OutputInterface $output = null )
    {
        $parameter = $this->entityManager
            ->getRepository( Parameter::class )
            ->findOneByName( self::PARAMETER_NAME );

        if( is_null( $parameter ) )
        {
            $parameter = new Parameter();
            $parameter->setName( self::PARAMETER_NAME );
            $parameter->setValue( self::PARAMETER_ENABLED );
        }

        // Check that $data is already serialized
        $dataUnserialize = @unserialize( $data );
        if( $dataUnserialize === false )
        {
            $parameter->setParams( $data );
        }
        else
        {
            $parameter->setParams( $dataUnserialize );
        }

        $this->entityManager->persist( $parameter );
        $this->entityManager->flush();

        return true;
    }

    public function showData( OutputInterface $output = null )
    {
        $paramValue = $this->getData( $output );

        return print_r( $paramValue, true );
    }
}