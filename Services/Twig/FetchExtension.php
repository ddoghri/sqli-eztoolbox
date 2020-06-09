<?php

namespace SQLI\EzToolboxBundle\Services\Twig;

use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\Core\MVC\Symfony\View\ViewManagerInterface;
use SQLI\EzToolboxBundle\Services\FetchHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FetchExtension extends AbstractExtension
{
    /** @var FetchHelper */
    private $fetchHelper;
    /** @var ViewManagerInterface */
    private $viewManager;
    /** @var Repository */
    private $repository;

    public function __construct( FetchHelper $fetchHelper, ViewManagerInterface $viewManager, Repository $repository )
    {
        $this->fetchHelper = $fetchHelper;
        $this->viewManager = $viewManager;
        $this->repository  = $repository;
    }

    public function getFunctions()
    {
        return
            [
                new TwigFunction( 'render_children', [ $this, 'renderChildren', [ 'is_safe' => [ 'all' ] ] ] ),
                new TwigFunction( 'fetch_children', [ $this, 'fetchChildren' ] ),
                new TwigFunction( 'fetch_ancestor', [ $this, 'fetchAncestor' ] ),
                new TwigFunction( 'fetch_content', [ $this, 'fetchContent' ] ),
                new TwigFunction( 'fetch_location', [ $this, 'fetchLocation' ] ),
            ];
    }

    /**
     * Use ViewController:viewLocation to generate display of children (eventually filtered with $filterContentClass) of a $location in specified $viewType
     * Some $parameters can be passed to template
     *
     * @param $parentLocation
     * @param $viewType
     * @param $filterContentClass
     * @param $parameters
     * @return string
     * @throws InvalidArgumentException
     */
    public function renderChildren( $parentLocation, $viewType = ViewManagerInterface::VIEW_TYPE_LINE, $filterContentClass = null, $parameters = array() )
    {
        // Fetch children of $location
        $children = $this->fetchHelper->fetchChildren( $parentLocation, $filterContentClass );

        $render = "";

        end( $children );
        $lastKey = key( $children );
        reset( $children );
        $firstKey = key( $children );

        foreach( $children as $index => $child )
        {
            $isfirst = $index === $firstKey;
            $islast  = $index === $lastKey;
            // Define specific parameters
            $specificParameters =
                [
                    'isFirst' => $isfirst,
                    'isLast'  => $islast,
                    'index'   => $index,
                ];

            $parameters['location'] = $child;
            $content                = $child->getContent();
            $parameters['content']  = $content;
            $render                 .= $this->viewManager->renderContent( $content, $viewType, array_merge( $parameters, $specificParameters ) );
        }

        return $render;
    }

    /**
     * @param $parentLocation
     * @param $contentClass
     * @return array
     * @throws InvalidArgumentException
     */
    public function fetchChildren( $parentLocation, $contentClass = null )
    {
        return $this->fetchHelper->fetchChildren( $parentLocation, $contentClass );
    }

    /**
     * Fetch ancestor of $location with specified $contentType
     *
     * @param Location|int $location
     * @param string       $contentType
     * @return Location|null
     * @throws InvalidArgumentException
     */
    public function fetchAncestor( $location, $contentType )
    {
        return $this->fetchHelper->fetchAncestor( $location, $contentType );
    }

    /**
     * @param int $contentId
     * @return Content
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function fetchContent( $contentId )
    {
        return $this->repository->getContentService()->loadContent( intval( $contentId ) );
    }

    /**
     * @param int $locationId
     * @return Location
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function fetchLocation( $locationId )
    {
        return $this->repository->getLocationService()->loadLocation( intval( $locationId ) );
    }

    public function getName()
    {
        return 'sqli_twig_extension_fetch';
    }
}