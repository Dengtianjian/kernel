<?php

namespace kernel\Traits\Model;

trait FilesModelTrait
{
  function add($FileKey, $SourceFileName, $SaveFileName, $FilePath, $FileSize, $Extension, $OwnerId = null, $ACL = 'private', $Remote = false, $BelongsId = null, $BelongsType = null, $Width = 0, $Height = 0)
  {
    return $this->insert(array_filter([
      "key" => $FileKey,
      "sourceFileName" => $SourceFileName,
      "fileName" => $SaveFileName,
      "filePath" => $FilePath,
      "fileSize" => $FileSize,
      "extension" => $Extension,
      "remote" => $Remote,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
      "ownerId" => $OwnerId,
      "width" => $Width,
      "height" => $Height,
      "acl" => $ACL
    ], function ($item) {
      return !is_null($item);
    }));
  }
  function save($Data, $FileKey = null, $Id = null)
  {
    return $this->filterNullWhere([
      "id" => $Id,
      "key" => $FileKey
    ])->update($Data);
  }
  function updateBelongs($BelongsId = null, $BelongsType = null, $FileKey = null, $Id = null)
  {
    return $this->save([
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
    ], $FileKey, $Id);
  }
  function item($FileKey = null, $BelongsId = null, $BelongsType, $OwnerId = null, $Id = null)
  {
    return $this->filterNullWhere([
      "id" => $Id,
      "key" => $FileKey,
      "ownerId" => $OwnerId,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
    ])->getOne();
  }
  private $ListTotal = 0;
  function listTotal()
  {
    return $this->ListTotal;
  }
  function list($Page = 1, $PerPage = 10, $FileKey = null, $BelongsId = null, $BelongsType, $OwnerId = null, $Id = null)
  {
    $this->ListTotal = $this->filterNullWhere([
      "id" => $Id,
      "key" => $FileKey,
      "ownerId" => $OwnerId,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
    ])->reset(false)->count();

    $this->page($Page, $PerPage);

    return $this->getAll();
  }
  function remove($directly = false, $FileKey = null, $BelongsId = null, $BelongsType, $OwnerId = null, $Id = null)
  {
    return $this->filterNullWhere([
      "id" => $Id,
      "key" => $FileKey,
      "ownerId" => $OwnerId,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
    ])->delete($directly);
  }
}
