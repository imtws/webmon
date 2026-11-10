import asyncio
import os
import signal


async def communicate_with_timeout(proc, timeout):
    communication = asyncio.ensure_future(proc.communicate())
    try:
        return await asyncio.wait_for(asyncio.shield(communication), timeout)
    except (asyncio.TimeoutError, asyncio.CancelledError):
        try:
            os.killpg(proc.pid, signal.SIGKILL)
        except ProcessLookupError:
            pass
        await communication
        raise
