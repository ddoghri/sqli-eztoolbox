<?php

namespace SQLI\EzToolboxBundle\Services;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use SQLI\EzToolboxBundle\Annotations\SQLIAnnotationManager;
use SQLI\EzToolboxBundle\Classes\Filter;

class EntityHelper
{
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var SQLIAnnotationManager */
    private $annotationManager;
    /** @var FilterEntityHelper */
    private $filterEntityHelper;

    public function __construct( EntityManagerInterface $entityManager, SQLIAnnotationManager $annotationManager,
                                 FilterEntityHelper $filterEntityHelper )
    {
        $this->entityManager      = $entityManager;
        $this->annotationManager  = $annotationManager;
        $this->filterEntityHelper = $filterEntityHelper;
    }

    /**
     * Get all classes annotated with SQLIClassAnnotation interface
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getAnnotatedClasses()
    {
        $annotatedClasses = $this->annotationManager->getAnnotatedClasses();

        foreach( $annotatedClasses as $annotatedFQCN => &$annotatedClass )
        {
            $annotatedClass['count'] = $this->count( $annotatedFQCN );
        }

        return $annotatedClasses;
    }

    /**
     * Get a class annotated with SQLIClassAnnotation interface from her FQCN
     *
     * @param $fqcn
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function getAnnotatedClass( $fqcn )
    {
        $annotatedClasses = $this->getAnnotatedClasses();

        return array_key_exists( $fqcn, $annotatedClasses ) ? $annotatedClasses[$fqcn] : null;
    }

    /**
     * Get an entity with her information and elements
     *
     * @param string     $fqcn
     * @param bool       $fetchElements
     * @param bool|array $sort Array( 'column_name' => '', 'order' => 'ASC|DESC' )
     * @return mixed
     * @throws \ReflectionException
     */
    public function getEntity( $fqcn, $fetchElements = true, $sort = false )
    {
        $annotatedClass['fqcn']  = $fqcn;
        $annotatedClass['class'] = $this->getAnnotatedClass( $fqcn );

        if( $fetchElements )
        {
            // Prepare a filter (only properties flagged as visible or without this annotation) for findAll
            $filteredColums = [];
            foreach( $annotatedClass['class']['properties'] as $propertyName => $propertyInfos )
            {
                if( $propertyInfos['visible'] )
                {
                    $filteredColums[] = $propertyName;
                }
            }

            // Get filter in session if exists
            $filter = $this->filterEntityHelper->getFilter( $fqcn );

            // Get all elements
            $annotatedClass['elements'] = $this->findAll( $fqcn, $filteredColums, $filter, $sort );
        }

        return $annotatedClass;
    }

    /**
     * Retrieve all lines in SQL table
     *
     * @param string      $entityClass FQCN
     * @param array|null  $filteredColums
     * @param Filter|null $filter
     * @param bool|array  $sort Array( 'column_name' => '', 'order' => 'ASC|DESC' )
     * @return array
     */
    public function findAll( $entityClass, $filteredColums = null, $filter = null, $sort = false )
    {
        /** @var $repository EntityRepository */
        $repository   = $this->entityManager->getRepository( $entityClass );
        $queryBuilder = $repository->createQueryBuilder( 'entity' );

        // In case of filtering columns
        if( is_array( $filteredColums ) )
        {
            array_walk( $filteredColums, function( &$columnName )
            {
                $columnName = "entity.$columnName";
            } );
            $select = implode( ",", $filteredColums );

            // Change SELECT clause
            $queryBuilder->select( $select );
        }

        // Filter
        if( !is_null( $filter ) )
        {
            // Add clause 'where'
            $queryBuilder->andWhere( sprintf( "entity.%s %s :value",
                                              $filter->getColumnName(),
                                              array_search( $filter->getOperand(), Filter::OPERANDS_MAPPING ) ) );

            $value = $filter->getValue();

            // Add % around value if operand is LIKE or NOT LIKE
            if( stripos( $filter->getOperand(), 'LIKE' ) !== false )
            {
                $value = "%" . $value . "%";
            }

            // Bind parameter
            $queryBuilder->setParameter( 'value', $value );
        }

        // Sort
        if( $sort !== false )
        {
            $queryBuilder->orderBy( "entity." . $sort['column_name'], ( $sort['order'] == "ASC" ? "ASC" : "DESC" ) );
        }

        // Return results as array (ignore accessibility of properties)
        return $queryBuilder->getQuery()->getArrayResult();
    }

    /**
     * Count number of element for an entity
     *
     * @param string $entityClass FQCN
     * @return integer
     */
    public function count( $entityClass )
    {
        return $this->entityManager->getRepository( $entityClass )->count( [] );
    }

    /**
     * Remove an element
     * $findCriteria = ['columnName' => 'value']
     *
     * @param string $entityClass FQCN
     * @param array  $findCriteria
     */
    public function remove( $entityClass, $findCriteria )
    {
        $element = $this->findOneBy( $entityClass, $findCriteria );
        if( !is_null( $element ) )
        {
            $this->entityManager->remove( $element );
            $this->entityManager->flush();
        }
    }

    /**
     * Find one element
     *
     * @param $entityClass
     * @param $findCriteria
     * @return object|null
     */
    public function findOneBy( $entityClass, $findCriteria )
    {
        return $this->entityManager->getRepository( $entityClass )->findOneBy( $findCriteria );
    }

    /**
     * @param $object
     * @param $property_name
     * @return false|string
     */
    public function attributeValue( $object, $property_name )
    {
        if( $object[$property_name] instanceof \DateTime )
        {
            // Datetime doesn't have a __toString method
            return date_format( $object[$property_name], "c" );
        }
        elseif ($object[$property_name] instanceof  \stdClass)
        {
           return json_encode($object[$property_name]);
        }
        return strval( $object[$property_name] );
    }
}
