"""Independent official WordPress cores; never selected by snapshot PHP."""
import hashlib
import io
import tarfile
import urllib.request

CORES = {
    '6.9': '5b36390233e32fef68cb5f66435bb32bdd50e0b3dfa5750aceb2de3c5993d720',
    '7.1.4': '881e53a4681ff2cd911651b11cccdede5ae477ddf0765af5d6a1a8b219a1d835',
}


def verify_core(data, version):
    if version not in CORES or len(data) > 40*1024*1024 or hashlib.sha256(data).hexdigest() != CORES[version]:
        raise ValueError('independent WordPress core hash refused')
    with tarfile.open(fileobj=io.BytesIO(data)) as archive:
        seen = set()
        size = 0
        for member in archive:
            if ((member.name != 'wordpress' and not member.name.startswith('wordpress/')) or
                    member.name in seen or '..' in member.name.split('/') or
                    not (member.isreg() or member.isdir())):
                raise ValueError('independent core member refused')
            seen.add(member.name)
            size += member.size
            if len(seen) > 30000 or size > 256*1024*1024:
                raise ValueError('independent core bound exceeded')
        if not {'wordpress/wp-load.php', 'wordpress/wp-settings.php', 'wordpress/index.php'} <= seen:
            raise ValueError('independent core incomplete')
    return data


def download_core(version):
    if version not in CORES:
        raise ValueError('unapproved WordPress core version')
    with urllib.request.urlopen('https://wordpress.org/wordpress-' + version + '.tar.gz', timeout=45) as response:
        data = response.read(40*1024*1024+1)
    return verify_core(data, version)
