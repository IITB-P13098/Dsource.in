/*
 * simple ring buffer
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

#ifndef ringbuffer_h
#define ringbuffer_h

class ringbuffer {

protected:
  unsigned char* fifo;

  volatile unsigned int size;
  volatile unsigned int used;
  volatile unsigned int begin; //! first available sign
  volatile unsigned int end;   //! oldest packet

  virtual void lock() {};
  virtual void unlock() {};

public:
  ringbuffer(unsigned int buffersize = 8000);
  ringbuffer(unsigned char* data, unsigned int len);

  virtual ~ringbuffer();

  unsigned int addData(const unsigned char* data, unsigned int len);
  unsigned int getData(unsigned char* data, unsigned int len);

  unsigned int getAvailable();
  unsigned int getUsed();

  // read newest nBytes
  unsigned int peekBack(unsigned char* data, unsigned int len);

  // read oldest nBytes
  unsigned int peekFront(unsigned char* data, unsigned int len);

  // delete the oldest len bytes
  unsigned int inc(unsigned int len);

  void clean();
};

#endif
