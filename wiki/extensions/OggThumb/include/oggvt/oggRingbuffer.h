/*
 * Ringbuffer to prebuffer an ogg file
 *
 * Copyright (C) 2005-2009 Joern Seger
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

/* History:
    01 2008: initial version is taken from the streamnik server project (JS)
*/
#ifndef oggRingbuffer_h
#define oggRingbuffer_h

#include "ringbuffer.h"
#include "oggHeader.h"

class OggRingbuffer : public ringbuffer {

protected:
  void dump();

public:
  OggRingbuffer(unsigned int buffersize = 64000);
  OggRingbuffer(unsigned char* data, unsigned int len);
  virtual ~OggRingbuffer();

  bool getNextPageLength(unsigned int& length, int pageNum=1);
  bool getNextPage(unsigned char*& data, unsigned int& length);
  bool getNextPages(unsigned char*& data, unsigned int& length, unsigned int pageNum);

};


#endif
