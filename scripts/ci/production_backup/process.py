"""Bounded process reader adapted from the accepted CP5 transport boundary."""
import os
import selectors
import signal
import subprocess
import tempfile
import time

class ProcessError(ValueError): pass

def run(argv,*,env,timeout=90,input_data=b'',limit=16384):
    if not 0<timeout<=180 or len(input_data)>16384: raise ProcessError('process settings')
    with tempfile.TemporaryFile() as source:
        source.write(input_data);source.seek(0)
        child=subprocess.Popen(argv,stdin=source,stdout=subprocess.PIPE,stderr=subprocess.PIPE,
            env=env,start_new_session=True,close_fds=True)
        stdout,stderr=bytearray(),bytearray();deadline=time.monotonic()+timeout
        try:
            with selectors.DefaultSelector() as selector:
                selector.register(child.stdout,selectors.EVENT_READ,stdout)
                selector.register(child.stderr,selectors.EVENT_READ,stderr)
                while selector.get_map():
                    left=deadline-time.monotonic()
                    if left<=0: raise ProcessError('process timeout')
                    for key,_ in selector.select(min(left,0.25)):
                        block=os.read(key.fileobj.fileno(),4096)
                        if not block: selector.unregister(key.fileobj);continue
                        if len(key.data)+len(block)>limit: raise ProcessError('process output limit')
                        key.data.extend(block)
            left=deadline-time.monotonic()
            if left<=0: raise ProcessError('process timeout')
            return subprocess.CompletedProcess(argv,child.wait(timeout=left),bytes(stdout),bytes(stderr))
        finally:
            try: os.killpg(child.pid,signal.SIGKILL)
            except ProcessLookupError: pass
            child.wait(timeout=5);child.stdout.close();child.stderr.close()
