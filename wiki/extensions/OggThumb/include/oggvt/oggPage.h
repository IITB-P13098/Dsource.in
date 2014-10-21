/*
 * OggPage will carry all relevant information of an ogg page
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

#ifndef OGGPAGE_H_
#define OGGPAGE_H_

#include <string>
/*
#ifdef HAVE_LIBOGG
#include <ogg/ogg.h>
#endif
*/

#include "refObject.h"
#include "definition.h"

/// class to store one ogg page
/** this class is easy to handle, as it only carries the
 *  data area that starts with "OggS".
 *  The toLibogg() method should be called only if an
 *  ogg_page is needed. It will NOT provide a deep copy
 *  so that the data will be lost, when the object is
 *  deleted. */
class OggPageInternal {

public:
  //! pointer to the packet data
  uint8* data;

  //! header length
  uint32 headerLength;

  //! body length
  uint32 bodyLength;

  //! internal information: number of stream associated by the decoder
  uint8 streamNo;

  //! internal information: unused page
  bool  empty;

  OggPageInternal();
  OggPageInternal(uint8* data, uint32 headerLength, uint32 bodyLength);
  virtual ~OggPageInternal();

  /* actually we will not create an interface to the original ogg lib
  ogg_page toLibogg();
  void fromLibogg(ogg_page page);
  */
};

class OggPage : public RefObject<OggPageInternal> {

public:
  OggPage();
  OggPage(const OggPage& page);
  OggPage(OggPageInternal* pagePtr);
  virtual ~OggPage();

  OggPage& operator=(const OggPage& page);

  //! Is this page continued ?
  bool     isContinued();

  //! Is this page a "Begin of Stream" page ?
  bool     isBOS();

  //! Is this page an "End of Stream" page ?
  /*! Every stream within a file (e.g. audio stream and video stream)
    has it's own eos flag */
  bool     isEOS();

  bool     isEmpty();

  void     setContinued();

  void     setEOS();
  void     unsetEOS();

  void     setBOS();
  void     unsetBOS();

  /* what ogg version is this stream */
  uint32   version();
  uint32   packets();
  int64    granulepos();
  uint32   serialno();
  uint32   pageno();

  void     createCRC();

  uint8    getStreamNo();
  void     setStreamNo(uint8 streamNo);

  uint32   length();
  uint8*   data();

  OggPage  clone();

  std::string print(uint8 level);
};

#endif /*OGGPAGE_H_*/
