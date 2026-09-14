// AudioWorklet: bounded mono PCM chunks, never connects microphone audio to speakers.
class PodcastRecorder extends AudioWorkletProcessor {
  constructor(){super();this.recording=false;this.buffer=new Int16Array(4096);this.offset=0;this.port.onmessage=e=>{this.recording=e.data===true;this.offset=0;};}
  process(inputs){
    const channels=inputs[0];
    if(this.recording&&channels?.[0]){
      for(let i=0;i<channels[0].length;i++){
        let sample=0;for(const channel of channels)sample+=channel[i]/channels.length;
        sample=Math.max(-1,Math.min(1,sample));this.buffer[this.offset++]=sample<0?sample*32768:sample*32767;
        if(this.offset===this.buffer.length){const output=this.buffer.buffer;this.port.postMessage(output,[output]);this.buffer=new Int16Array(4096);this.offset=0;}
      }
    }
    return true;
  }
}
registerProcessor('podcast-recorder',PodcastRecorder);
