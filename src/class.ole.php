<?php

/*
 * Copyleft 2002 Johann Hanne
 *
 * This is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this software; if not, write to the
 * Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA  02111-1307 USA
 */

/*
 * This is the OLE::Storage_Lite Perl package ported to PHP
 * OLE::Storage_Lite was written by Kawai Takanori, kwitknr@cpan.org
 */

require_once "functions.ole.php";
require_once "class.ole_pps.php";

class ole {

protected $sFile;

#------------------------------------------------------------------------------
# new OLE::Storage_Lite
#------------------------------------------------------------------------------
public function __construct($sFile) {
  $this->sFile=$sFile;
}

#------------------------------------------------------------------------------
# getPpsTree: OLE::Storage_Lite
#------------------------------------------------------------------------------
public function getPpsTree($bData=false) {
#0.Init
  $rhInfo = $this->_initParse($this->sFile);
  if (!$rhInfo) {
    return false;
  }
#1. Get Data
  list($oPps) = $this->_getPpsTree(0, $rhInfo, $bData);
//  close(IN);
  return $oPps;
}

#------------------------------------------------------------------------------
# getSearch: OLE::Storage_Lite
#------------------------------------------------------------------------------
public function getPpsSearch($raName, $bData=false, $iCase=false) {
#0.Init
  $rhInfo = $this->_initParse($this->sFile);
  if (!$rhInfo) {
    return false;
  }
#1. Get Data
  $aList = $this->_getPpsSearch(0, $rhInfo, $raName, $bData, $iCase);
//  close(IN);
  return $aList;
}

#------------------------------------------------------------------------------
# getNthPps: OLE::Storage_Lite
#------------------------------------------------------------------------------
public function getNthPps($iNo, $bData=false) {
#0.Init
  $rhInfo = $this->_initParse($this->sFile);
  if (!$rhInfo) {
    return false;
  }
#1. Get Data
  $oPps = $this->_getNthPps($iNo, $rhInfo, $bData);
//  close IN;
  return $oPps;
}

#------------------------------------------------------------------------------
# _initParse: OLE::Storage_Lite
#------------------------------------------------------------------------------
private function _initParse($sFile) {
  //$oIo;
#1. $sFile is a resource (hopefully a file resource)
  if (is_resource($sFile)) {
    $oIo=$sFile;
  }
#2. $sFile is a filename string
  else {
    $oIo=fopen($sFile, "rb");
    if ($oIo===false) {
      return false;
    }
  }

  return $this->_getHeaderInfo($oIo);
}

#------------------------------------------------------------------------------
# _getPpsTree: OLE::Storage_Lite
#------------------------------------------------------------------------------
private function _getPpsTree($iNo, $rhInfo, $bData, &$raDone=false) {
  if($raDone!==false) {
    if(in_array($iNo, $raDone)) {
      return array();
    }
  } else {
    $raDone=array();
  }
  array_push($raDone, $iNo);

  $iRootBlock = $rhInfo->_ROOT_START;
#1. Get Information about itself
  $oPps = $this->_getNthPps($iNo, $rhInfo, $bData);
  if ($oPps===false) {
    return [];
  }
#2. Child
  if($oPps->DirPps != 0xFFFFFFFF) {
    $aChildL = $this->_getPpsTree($oPps->DirPps, $rhInfo, $bData, $raDone);
    $oPps->Child =& $aChildL;
  } else {
    $oPps->Child = false;
  }
#3. Previous,Next PPSs
  $aList = array();
  if ($oPps->PrevPps != 0xFFFFFFFF) {
    array_push($aList, $this->_getPpsTree($oPps->PrevPps, $rhInfo, $bData, $raDone));
  }
  array_push($aList, $oPps);
  if ($oPps->NextPps != 0xFFFFFFFF) {
    array_push($aList, $this->_getPpsTree($oPps->NextPps, $rhInfo, $bData, $raDone));
  }
  return $aList;
}

#------------------------------------------------------------------------------
# _getPpsSearch: OLE::Storage_Lite
#------------------------------------------------------------------------------
private function _getPpsSearch($iNo, $rhInfo, $raName, $bData, $iCase, &$raDone=false) {
  $iRootBlock = $rhInfo->_ROOT_START;
  //my @aRes;
#1. Check it self

  if($raDone!==false) {
    if(in_array($iNo, $raDone)) {
      return [];
    }
  } else {
    $raDone=[];
  }
  array_push($raDone, $iNo);

  $oPps = $this->_getNthPps($iNo, $rhInfo, false);
  if ($oPps===false) {
    return [];
  }

  $found=false;
  foreach ($raName as $cmp) {
    if (($iCase && strcasecmp($oPps->Name, $cmp)==0) ||
        strcmp($oPps->Name, $cmp)==0) {
      $found=true;
      break;
    }
  }

  if ($found) {
    if ($bData) {
      $oPps = $this->_getNthPps($iNo, $rhInfo, $bData);
    }
    $aRes = array($oPps);
  } else {
    $aRes = array();
  }
#2. Check Child, Previous, Next PPSs
  if ($oPps->DirPps != 0xFFFFFFFF) {
    array_push($aRes, $this->_getPpsSearch($oPps->DirPps,  $rhInfo, $raName, $bData, $iCase, $raDone));
  }
  if ($oPps->PrevPps != 0xFFFFFFFF) {
    array_push($aRes, $this->_getPpsSearch($oPps->PrevPps, $rhInfo, $raName, $bData, $iCase, $raDone));
  }
  if ($oPps->NextPps != 0xFFFFFFFF) {
    array_push($aRes, $this->_getPpsSearch($oPps->NextPps, $rhInfo, $raName, $bData, $iCase, $raDone));
  }
  return $aRes;
}

#===================================================================
# Get Header Info (BASE Informain about that file)
#===================================================================
private function _getHeaderInfo($FILE) {
  //my($iWk);
  $rhInfo = new \stdClass();
  $rhInfo->_FILEH_ = $FILE;
  //my $sWk;
#0. Check ID
  fseek($rhInfo->_FILEH_, 0, SEEK_SET);
  $sWk=fread($rhInfo->_FILEH_, 8);
  if ($sWk!="\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
    return false;
  }
#BIG BLOCK SIZE
  $iWk = $this->_getInfoFromFile($rhInfo->_FILEH_, 0x1E, 2, "v");
  if ($iWk===false) {
    return false;
  }
  $rhInfo->_BIG_BLOCK_SIZE = pow(2, $iWk);
#SMALL BLOCK SIZE
  $iWk = $this->_getInfoFromFile($rhInfo->_FILEH_, 0x20, 2, "v");
  if ($iWk===false) {
    return false;
  }
  $rhInfo->_SMALL_BLOCK_SIZE = pow(2, $iWk);
#BDB Count
  $iWk = $this->_getInfoFromFile($rhInfo->_FILEH_, 0x2C, 4, "V");
  if ($iWk===false) {
    return false;
  }
  $rhInfo->_BDB_COUNT = $iWk;
#START BLOCK
  $iWk = $this->_getInfoFromFile($rhInfo->_FILEH_, 0x30, 4, "V");
  if ($iWk===false) {
    return false;
  }
  $rhInfo->_ROOT_START = $iWk;
#MIN SIZE OF BB
#  $iWk = _getInfoFromFile($rhInfo->_FILEH_, 0x38, 4, "V");
#  if ($iWk===false) {
#    return false;
#  }
#  $rhInfo->_MIN_SIZE_BB = $iWk;
#SMALL BD START
  $iWk = $this->_getInfoFromFile($rhInfo->_FILEH_, 0x3C, 4, "V");
  if ($iWk===false) {
    return false;
  }
  $rhInfo->_SBD_START = $iWk;
#SMALL BD COUNT
  $iWk = $this->_getInfoFromFile($rhInfo->_FILEH_, 0x40, 4, "V");
  if ($iWk===false) {
    return false;
  }
  $rhInfo->_SBD_COUNT = $iWk;
#EXTRA BBD START
  $iWk = $this->_getInfoFromFile($rhInfo->_FILEH_, 0x44, 4, "V");
  if ($iWk===false) {
    return false;
  }
  $rhInfo->_EXTRA_BBD_START = $iWk;
#EXTRA BD COUNT
  $iWk = $this->_getInfoFromFile($rhInfo->_FILEH_, 0x48, 4, "V");
  if ($iWk===false) {
    return false;
  }
  $rhInfo->_EXTRA_BBD_COUNT = $iWk;
#GET BBD INFO
  $rhInfo->_BBD_INFO= $this->_getBbdInfo($rhInfo);
#GET ROOT PPS
  $oRoot = $this->_getNthPps(0, $rhInfo, false);
  if ($oRoot===false) {
    return false;
  }
  $rhInfo->_SB_START = $oRoot->StartBlock;
  $rhInfo->_SB_SIZE  = $oRoot->Size;
  return $rhInfo;
}

#------------------------------------------------------------------------------
# _getInfoFromFile
#------------------------------------------------------------------------------
private function _getInfoFromFile($FILE, $iPos, $iLen, $sFmt) {
  //my($sWk);
  if (!$FILE) {
    return false;
  }
  if (fseek($FILE, $iPos, SEEK_SET)!==0) {
    return false;
  }
  $sWk=fread($FILE, $iLen);
  $data=unpack($sFmt."ret", $sWk);
  return $data["ret"];
}

#------------------------------------------------------------------------------
# _getBbdInfo
#------------------------------------------------------------------------------
private function _getBbdInfo($rhInfo) {
  $aBdList = array();
  $iBdbCnt = $rhInfo->_BDB_COUNT;
  //my $iGetCnt;
  //my $sWk;
  $i1stCnt = floor(($rhInfo->_BIG_BLOCK_SIZE - 0x4C) / LongIntSize);
  $iBdlCnt = floor($rhInfo->_BIG_BLOCK_SIZE / LongIntSize) - 1;

#1. 1st BDlist
  fseek ($rhInfo->_FILEH_, 0x4C, SEEK_SET);
  $iGetCnt = ($iBdbCnt < $i1stCnt) ? $iBdbCnt : $i1stCnt;
  $sWk=fread($rhInfo->_FILEH_, LongIntSize*$iGetCnt);
  $data=unpack("V".$iGetCnt."int", $sWk);
  for ($c=0;$c<$iGetCnt;$c++) {
    array_push ($aBdList, $data["int$c"]);
  }
  $iBdbCnt -= $iGetCnt;
#2. Extra BDList
  $iBlock = $rhInfo->_EXTRA_BBD_START;
  while(($iBdbCnt> 0) && $this->_isNormalBlock($iBlock)){
    $this->_setFilePos($iBlock, 0, $rhInfo);
    $iGetCnt= ($iBdbCnt < $iBdlCnt) ? $iBdbCnt : $iBdlCnt;
    $sWk=fread($rhInfo->_FILEH_, LongIntSize*$iGetCnt);
    $data=unpack("V".$iGetCnt."int", $sWk);
    for ($c=0;$c<$iGetCnt;$c++) {
      array_push ($aBdList, $data["int$c"]);
    }
    $iBdbCnt -= $iGetCnt;
    $sWk=fread($rhInfo->_FILEH_, LongIntSize);
    $data=unpack("Vint", $sWk);
    $iBlock = $data["int"];
  }
#3.Get BDs
  $aWk=array();
  $hBd=array();
  $iBlkNo = 0;
  $iBdCnt = floor($rhInfo->_BIG_BLOCK_SIZE / LongIntSize);
  foreach ($aBdList as $iBdl) {
    $this->_setFilePos($iBdl, 0, $rhInfo);
    $sWk=fread($rhInfo->_FILEH_, $rhInfo->_BIG_BLOCK_SIZE);
    $aWk = unpack("V".$iBdCnt."int", $sWk);
    for ($i=0;$i<$iBdCnt;$i++, $iBlkNo++) {
      if($aWk["int".$i] != ($iBlkNo+1)) {
        $hBd["$iBlkNo"] = $aWk["int".$i];
      }
    }
  }
  return $hBd;
}

#------------------------------------------------------------------------------
# getNthPps (OLE::Storage_Lite)
#------------------------------------------------------------------------------
private function _getNthPps($iPos, $rhInfo, $bData) {
  $iPpsStart = $rhInfo->_ROOT_START;

  $iBaseCnt = $rhInfo->_BIG_BLOCK_SIZE / PpsSize;
  $iPpsBlock = floor($iPos / $iBaseCnt);
  $iPpsPos   = $iPos % $iBaseCnt;

  $iBlock = $this->_getNthBlockNo($iPpsStart, $iPpsBlock, $rhInfo);
  if (!$this->_isNormalBlock($iBlock)) {
    return false;
  }

  $this->_setFilePos($iBlock, PpsSize*$iPpsPos, $rhInfo);
  $sWk=fread($rhInfo->_FILEH_, PpsSize);
  if ($sWk===false || strlen($sWk)!==PpsSize) {
    return false;
  }
//TODO: substr() binary safe?
  $data=unpack("vint", substr($sWk, 0x40, 2));
  $iNmSize = $data["int"];
  $iNmSize = ($iNmSize > 2) ? $iNmSize - 2 : $iNmSize;
  $sNm= substr($sWk, 0, $iNmSize);
  $data=unpack("Cint", substr($sWk, 0x42, 2));
  $iType = $data["int"];
  $data=unpack("V3int", substr($sWk, 0x44, LongIntSize*3));
  $lPpsPrev = $data["int1"];
  $lPpsNext = $data["int2"];
  $lDirPps  = $data["int3"];
// TODO: Original lines terminated by commas: ?!
/*
  my @raTime1st = 
        (($iType == PpsType_Root) or ($iType == PpsType_Dir))? 
            OLEDate2Local(substr($sWk, 0x64, 8)) : undef ,
  my @raTime2nd = 
        (($iType == PpsType_Root) or ($iType == PpsType_Dir))? 
            OLEDate2Local(substr($sWk, 0x6C, 8)) : undef,
*/
  $raTime1st = (($iType == PpsType_Root) or ($iType == PpsType_Dir)) ?
               OLEDate2Local(substr($sWk, 0x64, 8)) : false;
  $raTime2nd = (($iType == PpsType_Root) or ($iType == PpsType_Dir)) ?
               OLEDate2Local(substr($sWk, 0x6C, 8)) : false;

  $data=unpack("V2int", substr($sWk, 0x74, 8));
  $iStart=$data["int1"];
  $iSize=$data["int2"];
  $sData = $bData ? $this->_getData($iType, $iStart, $iSize, $rhInfo) : false;
  return new ole_pps(
    $iPos,
    $sNm,
    $iType,
    $lPpsPrev,
    $lPpsNext,
    $lDirPps,
    $raTime1st,
    $raTime2nd,
    $iStart,
    $iSize,
    $sData
  );
}

#------------------------------------------------------------------------------
# _setFilePos (OLE::Storage_Lite)
#------------------------------------------------------------------------------
private function _setFilePos($iBlock, $iPos, $rhInfo) {
  fseek($rhInfo->_FILEH_, ($iBlock+1)*$rhInfo->_BIG_BLOCK_SIZE+$iPos,
        SEEK_SET);
}

#------------------------------------------------------------------------------
# _getNthBlockNo (OLE::Storage_Lite)
#------------------------------------------------------------------------------
private function _getNthBlockNo($iStBlock, $iNth, $rhInfo) {
  //my $iSv;
  $iNext = $iStBlock;
  for ($i=0; $i<$iNth; $i++) {
    $iSv = $iNext;
    $iNext = $this->_getNextBlockNo($iSv, $rhInfo);
    if (!$this->_isNormalBlock($iNext)) {
      return;
    }
  }
  return $iNext;
}

#------------------------------------------------------------------------------
# _getData (OLE::Storage_Lite)
#------------------------------------------------------------------------------
private function _getData($iType, $iBlock, $iSize, $rhInfo) {
  if ($iType == PpsType_File) {
    if($iSize < DataSizeSmall) {
        return $this->_getSmallData($iBlock, $iSize, $rhInfo);
    } else {
        return $this->_getBigData($iBlock, $iSize, $rhInfo);
    }
  } elseif($iType == PpsType_Root) {  #Root
    return $this->_getBigData($iBlock, $iSize, $rhInfo);
  } elseif($iType == PpsType_Dir) {  # Directory
    return false;
  }
}

#------------------------------------------------------------------------------
# _getBigData (OLE::Storage_Lite)
#------------------------------------------------------------------------------
private function _getBigData($iBlock, $iSize, $rhInfo) {
  //my($iRest, $sWk, $sRes);

  if (!$this->_isNormalBlock($iBlock)) {
    return '';
  }

  $iRest = $iSize;
  //my($i, $iGetSize, $iNext);
  $sRes = '';
/* TODO
  my @aKeys= sort({$a<=>$b} keys(%{$rhInfo->{_BBD_INFO}}));

  while ($iRest > 0) {
    my @aRes = grep($_ >= $iBlock, @aKeys);
    my $iNKey = $aRes[0];
    $i = $iNKey - $iBlock;
    $iNext = $rhInfo->{_BBD_INFO}{$iNKey};
    $this->_setFilePos($iBlock, 0, $rhInfo);
    my $iGetSize = ($rhInfo->{_BIG_BLOCK_SIZE} * ($i+1));
    $iGetSize = $iRest if($iRest < $iGetSize);
    $rhInfo->{_FILEH_}->read( $sWk, $iGetSize);
    $sRes .= $sWk;
    $iRest -= $iGetSize;
    $iBlock= $iNext;
  }
*/

  return $sRes;
}

#------------------------------------------------------------------------------
# _getNextBlockNo (OLE::Storage_Lite)
#------------------------------------------------------------------------------
private function _getNextBlockNo($iBlockNo, $rhInfo) {
  $iRes = $rhInfo->_BBD_INFO[$iBlockNo];
  return ($iRes!==false) ? $iRes : $iBlockNo+1;
}

#------------------------------------------------------------------------------
# _isNormalBlock (OLE::Storage_Lite)
# 0xFFFFFFFC : BDList, 0xFFFFFFFD : BBD, 
# 0xFFFFFFFE: End of Chain 0xFFFFFFFF : unused
#------------------------------------------------------------------------------
private function _isNormalBlock($iBlock) {
  return ($iBlock < 0xFFFFFFFC) ? 1 : false;
}

#------------------------------------------------------------------------------
# _getSmallData (OLE::Storage_Lite)
#------------------------------------------------------------------------------
private function _getSmallData($iSmBlock, $iSize, $rhInfo) {
  //my($sRes, $sWk);
  $iRest = $iSize;
  $sRes = '';
  while ($iRest > 0) {
    $this->_setFilePosSmall($iSmBlock, $rhInfo);
    $sWk=fread($rhInfo->_FILEH_, ($iRest >= $rhInfo->_SMALL_BLOCK_SIZE) ?
               $rhInfo->_SMALL_BLOCK_SIZE : $iRest);
    $sRes .= $sWk;
    $iRest -= $rhInfo->_SMALL_BLOCK_SIZE;
    $iSmBlock= $this->_getNextSmallBlockNo($iSmBlock, $rhInfo);
  }
  return $sRes;
}

#------------------------------------------------------------------------------
# _setFilePosSmall(OLE::Storage_Lite)
#------------------------------------------------------------------------------
private function _setFilePosSmall($iSmBlock, $rhInfo) {
  $iSmStart = $rhInfo->_SB_START;
  $iBaseCnt = $rhInfo->_BIG_BLOCK_SIZE / $rhInfo->_SMALL_BLOCK_SIZE;
  $iNth = floor($iSmBlock/$iBaseCnt);
  $iPos = $iSmBlock % $iBaseCnt;

  $iBlk = $this->_getNthBlockNo($iSmStart, $iNth, $rhInfo);
  $this->_setFilePos($iBlk, $iPos * $rhInfo->_SMALL_BLOCK_SIZE, $rhInfo);
}

#------------------------------------------------------------------------------
# _getNextSmallBlockNo (OLE::Storage_Lite)
#------------------------------------------------------------------------------
private function _getNextSmallBlockNo($iSmBlock, $rhInfo) {
  //my($sWk);

  $iBaseCnt = $rhInfo->_BIG_BLOCK_SIZE / LongIntSize;
  $iNth = floor($iSmBlock/$iBaseCnt);
  $iPos = $iSmBlock % $iBaseCnt;
  $iBlk = $this->_getNthBlockNo($rhInfo->_SBD_START, $iNth, $rhInfo);
  $this->_setFilePos($iBlk, $iPos * LongIntSize, $rhInfo);
  $sWk=fread($rhInfo->_FILEH_, LongIntSize);
  $data=unpack("Vint", $sWk);
  return $data["int"];
}

}

?>
