/*
 * RawMediaPacket class to carry a raw bunch of data
 *
 * Copyright (C) 2005-2008 Joern Seger
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

#ifndef RAWMEDIAPACKET_H_
#define RAWMEDIAPACKET_H_

#include "definition.h"
#include "refObject.h"

class RawMediaData {

protected:
  uint8* data;
  uint32 length;

public:
  RawMediaData();
  RawMediaData(uint8* data, uint32 length, bool copy);
  virtual ~RawMediaData();

  void   setData(uint8* data, uint32 length, bool copy);
  uint8* getData(uint32& length);
  uint8* getData();
  uint32 size();

};

class RawMediaPacket : public RefObject<RawMediaData> {

public:

  RawMediaPacket();
  RawMediaPacket(const RawMediaPacket& data);
  RawMediaPacket(RawMediaData* data);

  virtual ~RawMediaPacket();

  uint8* getData(uint32& length);
  uint8* getData();
  uint32 size();

};

#endif /*RAWMEDIAPACKET_H_*/
