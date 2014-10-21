//
// C++ Interface: effector
//
// Description:
//
//
// Author: Yorn <yorn@gmx.net>, (C) 2009
//
// Copyright: See COPYING file that comes with this distribution
//
//
#ifndef EFFECTOR_H
#define EFFECTOR_H

#include "rgbPlane.h"
/**
	@author Yorn <yorn@gmx.net>
*/
class Effector {

public:

  Effector();

  virtual ~Effector();

  virtual Effector& operator>>(RGBPlane& plane) = 0;

  virtual bool available() = 0;

};

#endif
