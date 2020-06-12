<?php

namespace SQLI\EzToolboxBundle\QueryType;

use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\Core\QueryType\OptionsResolverBasedQueryType;
use eZ\Publish\Core\QueryType\QueryType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentChildrenQueryType extends OptionsResolverBasedQueryType implements QueryType
{
    public static function getName()
    {
        return 'FetchContentChildren';
    }

    protected function doGetQuery( array $parameters )
    {
        $criteria =
            [
                new Query\Criterion\Visibility( Query\Criterion\Visibility::VISIBLE )
            ];
        if( isset( $parameters['content_types'] ) )
        {
            $criteria[] = new Query\Criterion\ContentTypeIdentifier( $parameters['content_types'] );
        }

        if( isset( $parameters['parent_location_id'] ) )
        {
            $criteria[] = new Query\Criterion\ParentLocationId( $parameters['parent_location_id'] );
        }

        return new LocationQuery( [
                                      'filter'      => new Query\Criterion\LogicalAnd( $criteria ),
                                      'sortClauses' =>
                                          [
                                              new Query\SortClause\DatePublished()
                                          ],
                                      'limit'       => $parameters['limit'],
                                  ] );
    }

    protected function configureOptions( OptionsResolver $resolver )
    {
        $resolver->setDefined( [ 'parent_location_id', 'content_types', 'limit' ] );
        $resolver->setAllowedTypes( 'parent_location_id', 'int' );
        $resolver->setAllowedTypes( 'content_types', 'string' );
        $resolver->setAllowedTypes( 'limit', 'int' );
        $resolver->setDefault( 'limit', 10 );
    }
}
