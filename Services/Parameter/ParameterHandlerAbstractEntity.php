<?php

namespace SQLI\EzToolboxBundle\Services\Parameter;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use SQLI\EzToolboxBundle\Exceptions\ParameterHandlerDataUnexpectedException;
use SQLI\EzToolboxBundle\Entity\Doctrine\Parameter;
use SQLI\EzToolboxBundle\Exceptions\ParameterHandlerUnknownParameterValueException;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ParameterHandlerAbstractEntity implements ParameterHandlerInterface
{
    const PARAMETER_NAME     = "";
    const PARAMETER_ENABLED  = "enabled";
    const PARAMETER_DISABLED = "disabled";
    /** @var EntityManager */
    private $entityManager;

    public function __construct( EntityManager $entityManager )
    {
        $this->entityManager = $entityManager;
    }

    public function listParameters()
    {
        return [
            self::PARAMETER_ENABLED,
            self::PARAMETER_DISABLED
        ];
    }

    public function setParameter( $paramName, $paramValue, $contentIds, OutputInterface $output = null )
    {
        if( $paramValue == self::PARAMETER_ENABLED || $paramValue == self::PARAMETER_DISABLED )
        {
            if( $parameter = $this->entityManager
                ->getRepository( Parameter::class )
                ->findOneByName( self::PARAMETER_NAME ) )
            {
                $parameter->setValue( $paramValue );

                $this->entityManager->persist( $parameter );
                $this->entityManager->flush();
                if( isset( $output ) )
                {
                    $output->writeln( "  Status : " . $parameter->getValue() );
                }

                return true;
            }
        }
        throw new ParameterHandlerUnknownParameterValueException( "Unsupported value parameter $paramValue" );
    }

    public function showParameter( $paramName, $paramValue, OutputInterface $output = null )
    {
        if( $parameter = $this->entityManager
            ->getRepository( Parameter::class )
            ->findOneByName( self::PARAMETER_NAME ) )
        {
            $output->writeln( "  Status : " . $parameter->getValue() );
        }
    }

    public function isEnabled( $params = null )
    {
        if( $parameter = $this->entityManager
            ->getRepository( Parameter::class )
            ->findOneByName( self::PARAMETER_NAME ) )
        {
            return $parameter->getValue() == self::PARAMETER_ENABLED;
        }

        return false;
    }

    /**
     * @param OutputInterface|null $output
     * @return mixed
     * @throws ParameterHandlerDataUnexpectedException
     */
    public function getData( OutputInterface $output = null )
    {
        if( $parameter = $this->entityManager
            ->getRepository( Parameter::class )
            ->findOneByName( self::PARAMETER_NAME ) )
        {
            return $parameter->getParams();
        }
    }

    /**
     * @param                      $data
     * @param OutputInterface|null $output
     * @return bool
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ParameterHandlerDataUnexpectedException
     */
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

    /**
     * @param OutputInterface|null $output
     * @return string|true
     * @throws ParameterHandlerDataUnexpectedException
     */
    public function showData( OutputInterface $output = null )
    {
        $paramValue = $this->getData( $output );

        return print_r( $paramValue, true );
    }
}