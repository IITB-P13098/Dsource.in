/*
 * OggPacket will carry all relevant information of an ogg packet
 *
 * Copyright (C) 2008 Joern Seger
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 */

#ifndef OGGPACKET_H_
#define OGGPACKET_H_

#include <string>
#ifdef HAVE_LIBOGG
#include <ogg/ogg.h>
#endif

#include "definition.h"
#include "refObject.h"
#include "oggTypes.h"

class OggPacketInternal : public ogg_packet {

public:

  enum PacketType {
    normal,
    bos,
    eos
  };

  /* information about the stream type and the stream No */
  OggType streamType;
  uint8   streamNo;
  bool    streamHeader;

  OggPacketInternal();
  OggPacketInternal(uint8* data, uint32 length, uint32 packetNo,
                    int64 granulePos=-1, PacketType packetType = normal);

  virtual ~OggPacketInternal();

  OggPacketInternal* clone();
};

class OggPacket : public RefObject<OggPacketInternal> {

public:
  OggPacket();
  OggPacket(const OggPacket& packet);
  OggPacket(OggPacketInternal* internalPacket);
  virtual ~OggPacket();

  OggPacket& operator=(const OggPacket& packet);

  int64   granulepos();

  void    setGranulepos(int64 pos);

  uint32  getPacketNo();
  uint8   getStreamNo();
  OggType getStreamType();

  void    setStreamType(OggType type);
  void    setStreamNo(int8 streamNo);
  void    setStreamHeader();

  bool    isBOS();
  bool    isEOS();
  bool    isStreamHeader();

  void    setBOS();
  void    unsetBOS();
  void    setEOS();
  void    unsetEOS();

  uint32 length();
  uint8* data();

  OggPacket clone();

  /*
    ogg_packet toLibogg();
    void fromLibogg(ogg_packet packet);
  */

  std::string print(uint8 level);

};

#endif /*OGGPACKET_H_*/
