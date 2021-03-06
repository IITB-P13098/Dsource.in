//
// C++ Interface: cmdlineextractor
//
// Description:
//
//
// Author: Yorn <yorn@gmx.net>, (C) 2009
//
// Copyright: See COPYING file that comes with this distribution
//
//
#ifndef CMDLINEEXTRACTOR_H
#define CMDLINEEXTRACTOR_H

#include <deque>
#include <vector>
#include <string>

#include "definition.h"
#include "oggComment.h"
#include "blendElement.h"

const std::string validChars ( "0123456789,x" );
const std::string
validTextChars ( "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 /\\.,=;:-_" );

class SlideshowElement {
public:
  enum SlideshowType {
    KenBurns,
    Crossfade,
    Plain,
    Blur
  };

  std::string    filename;
  float          duration;
  SlideshowType  type;
  uint32         startPosX;
  uint32         startPosY;
  uint32         startZoom;
  uint32         endPosX;
  uint32         endPosY;
  uint32         endZoom;
};

class CmdlineExtractor {
public:
  CmdlineExtractor();

  ~CmdlineExtractor();

  static void extractCommentPairs ( std::vector<OggComment>& list,
                                    const std::string& _argument, char tokenSeparator, char commentSeparator );

  static void extractUint32 ( std::deque<uint32>& list, const std::string& _argument,
                              char seperator );

  static void extractBlend ( std::vector<BlendElement>& list, const std::string& _argument,
                             char tokenSeparator, char valueSeparator );

  static uint32 atoi(const std::string& _argument);
  static float atof(const std::string& _argument);

  static uint32 getNextUint32(std::string& substring, char tokenSeparator);
  static std::string getNextString(std::string& substring, char tokenSeparator);

  static SlideshowElement extractSlideshow(const std::string& _argument, char tokenSeparator, SlideshowElement& defaultElement);
  static void extractCrossSequence(std::vector<std::string>& list, const std::string& _argument, char tokenSeparator);

};

#endif
