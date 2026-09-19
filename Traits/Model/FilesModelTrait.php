<?php

namespace kernel\Traits\Model;

trait FilesModelTrait
{
  function add($Key, $SourceFileName, $Name, $Path, $Size, $Extension, $OwnerId = null, $accessControl = 'private', $Remote = false, $BelongsId = null, $BelongsType = null, $Width = 0, $Height = 0, $Platform = "local")
  {
    return $this->insert(array_filter([
      "key" => $Key,
      "sourceFileName" => $SourceFileName,
      "name" => $Name,
      "path" => $Path,
      "size" => $Size,
      "extension" => $Extension,
      "remote" => $Remote,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
      "ownerId" => $OwnerId,
      "width" => $Width,
      "height" => $Height,
      "accessControl" => $accessControl,
      "platform" => $Platform
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
  function item($FileKey = null, $BelongsId = null, $BelongsType = null, $OwnerId = null, $Id = null, $Platform = null)
  {
    return $this->filterNullWhere([
      "id" => $Id,
      "key" => $FileKey,
      "ownerId" => $OwnerId,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
      "platform" => $Platform
    ])->getOne();
  }
  private $ListTotal = 0;
  function listTotal()
  {
    return $this->ListTotal;
  }
  function list($Page = 1, $PerPage = 10, $FileKey = null, $BelongsId = null, $BelongsType = null, $OwnerId = null, $Id = null, $Platform = null)
  {
    $this->ListTotal = $this->filterNullWhere([
      "id" => $Id,
      "key" => $FileKey,
      "ownerId" => $OwnerId,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
      "platform" => $Platform
    ])->reset(false)->count();

    $this->page($Page, $PerPage);

    return $this->getAll();
  }
  function remove($directly = false, $FileKey = null, $BelongsId = null, $BelongsType = null, $OwnerId = null, $Id = null, $Platform = null)
  {
    return $this->filterNullWhere([
      "id" => $Id,
      "key" => $FileKey,
      "ownerId" => $OwnerId,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
      "platform" => $Platform
    ])->delete($directly);
  }
  public function existItem($Key = null, $BelongsId = null, $BelongsType = null, $OwnerId = null, $Id = null, $Platform = null)
  {
    return $this->filterNullWhere([
      "id" => $Id,
      "key" => $Key,
      "ownerId" => $OwnerId,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
      "platform" => $Platform
    ])->exist();
  }
}
