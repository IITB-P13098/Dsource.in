#ifndef audioPacket_h
#define audioPacket_h

#include "definition.h"
#include "refObject.h"

class AudioPacketInternal {

protected:
  float** pcmData;
  uint32 length;
  uint8 channels;

  void initMem(uint8 channels, uint32 length);

public:

  AudioPacketInternal();
  AudioPacketInternal(const AudioPacketInternal& packet);
  AudioPacketInternal(uint8 channels, uint32 length);
  AudioPacketInternal(float** dataPtr, uint32 length, uint8 channels);
  virtual ~AudioPacketInternal();

  uint32 getLength() const;
  uint8 getChannels() const;
  float** getAllChannels() const;

  float* getDataOfChannel(uint8 channel) const;
  void setDataOfChannel(uint8 channel, float* data);

  void cleanup();
};

class AudioPacket : public RefObject<AudioPacketInternal> {

public:
  AudioPacket();
  AudioPacket(const AudioPacket& packet);
  AudioPacket(AudioPacketInternal* internalPacket);
  virtual ~AudioPacket();

  AudioPacketInternal* operator*();
  AudioPacket& operator=(const AudioPacket& packet);

  AudioPacket clone();

};

#endif
