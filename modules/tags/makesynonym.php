<?php

$http = eZHTTPTool::instance();

$tagID = (int) $Params['TagID'];
$convertAllowed = true;
$warning = '';
$error = '';

if ( $tagID <= 0 )
{
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

$tag = eZTagsObject::fetch( $tagID );
if ( !( $tag instanceof eZTagsObject ) )
{
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

if ( $tag->MainTagID != 0 )
{
    return $Module->redirectToView( 'makesynonym', array( $tag->MainTagID ) );
}

if ( $tag->getSubTreeLimitationsCount() > 0 )
{
    $convertAllowed = false;
    $error = ezpI18n::tr( 'extension/eztags/errors', 'Tag cannot be modified because it is being used as subtree limitation in one or more class attributes.' );
}
else
{
    if ( $http->hasPostVariable( 'DiscardButton' ) )
    {
        return $Module->redirectToView( 'id', array( $tagID ) );
    }

    if ( $tag->isInsideSubTreeLimit() )
    {
        $warning = ezpI18n::tr( 'extension/eztags/warnings', 'TAKE CARE: Tag is inside class attribute subtree limit(s). If moved outside those limits, it could lead to inconsistency as objects could end up with tags that they are not supposed to have.' );
    }

    if ( $http->hasPostVariable( 'SaveButton' ) )
    {
        if ( !( $http->hasPostVariable( 'MainTagID' ) && (int) $http->postVariable( 'MainTagID' ) > 0 ) )
        {
            $error = ezpI18n::tr( 'extension/eztags/errors', 'Selected target tag is invalid.' );
        }

        if ( empty( $error ) )
        {
            $mainTag = eZTagsObject::fetch( (int) $http->postVariable( 'MainTagID' ) );
            if ( !( $mainTag instanceof eZTagsObject ) )
            {
                $error = ezpI18n::tr( 'extension/eztags/errors', 'Selected target tag is invalid.' );
            }
        }

        if ( empty( $error ) && eZTagsObject::exists( $tag->ID, $tag->Keyword, $mainTag->ParentID ) )
        {
            $error = ezpI18n::tr( 'extension/eztags/errors', 'Tag/synonym with that name already exists in selected location.' );
        }

        if ( empty( $error ) )
        {
            $newParentTag = $mainTag->getParent();

            $db = eZDB::instance();
            $db->begin();

            if ( $tag->ParentID != $mainTag->ParentID )
            {
                $oldParentTag = $tag->getParent();
                if ( $oldParentTag instanceof eZTagsObject )
                {
                    $oldParentTag->updateModified();
                }
            }

            eZTagsObject::moveChildren( $tag, $mainTag );

            $synonyms = $tag->getSynonyms();
            foreach ( $synonyms as $synonym )
            {
                $synonym->ParentID = $mainTag->ParentID;
                $synonym->MainTagID = $mainTag->ID;
                $synonym->store();
            }

            $tag->ParentID = $mainTag->ParentID;
            $tag->MainTagID = $mainTag->ID;
            $tag->store();
            $tag->updatePathString( ( $newParentTag instanceof eZTagsObject ) ? $newParentTag : false );
            $tag->updateModified();

            $db->commit();

            return $Module->redirectToView( 'id', array( $tagID ) );
        }
    }
}

$tpl = eZTemplate::factory();

$tpl->setVariable( 'tag', $tag );
$tpl->setVariable( 'convert_allowed', $convertAllowed );
$tpl->setVariable( 'warning', $warning );
$tpl->setVariable( 'error', $error );

$Result = array();
$Result['content']    = $tpl->fetch( 'design:tags/makesynonym.tpl' );
$Result['ui_context'] = 'edit';
$Result['path']       = array();

$tempTag = $tag;
while ( $tempTag->hasParent() )
{
    $tempTag = $tempTag->getParent();
    $Result['path'][] = array( 'tag_id' => $tempTag->ID,
                               'text'   => $tempTag->Keyword,
                               'url'    => false );
}

$Result['path'] = array_reverse( $Result['path'] );
$Result['path'][] = array( 'tag_id' => $tag->ID,
                           'text'   => $tag->Keyword,
                           'url'    => false );

$contentInfoArray = array();
$contentInfoArray['persistent_variable'] = false;
if ( $tpl->variable( 'persistent_variable' ) !== false )
    $contentInfoArray['persistent_variable'] = $tpl->variable( 'persistent_variable' );

$Result['content_info'] = $contentInfoArray;

?>
