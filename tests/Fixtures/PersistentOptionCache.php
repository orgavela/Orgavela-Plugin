<?php
// Test-only cross-process cache. Never included in release artifacts.
final class PersistentOptionCache extends WP_Object_Cache {
    private int $site;
    public function __construct(private string $path) { parent::__construct(); $this->site=get_current_blog_id(); }
    public function switch_to_blog($blog_id) { parent::switch_to_blog($blog_id); $this->site=(int)$blog_id; }
    private function access(callable $callback): mixed {
        $file=fopen($this->path,'c+b');
        if (!$file || !flock($file, LOCK_EX)) throw new RuntimeException('Test cache unavailable');
        try {
            $bytes=stream_get_contents($file); $data=$bytes ? unserialize($bytes) : [];
            $result=$callback($data);
            rewind($file); ftruncate($file,0); fwrite($file,serialize($data)); fflush($file);
            return $result;
        } finally { flock($file,LOCK_UN); fclose($file); }
    }
    public function get($key,$group='default',$force=false,&$found=null) {
        if ($group!=='options') return parent::get($key,$group,$force,$found);
        return $this->access(function(&$data)use($key,&$found){$key=$this->site.':'.$key; $found=array_key_exists($key,$data); return $found ? $data[$key] : false;});
    }
    public function set($key,$value,$group='default',$expire=0) {
        if ($group!=='options') return parent::set($key,$value,$group,$expire);
        return $this->access(function(&$data)use($key,$value){$data[$this->site.':'.$key]=$value; return true;});
    }
    public function add($key,$value,$group='default',$expire=0) {
        if ($group!=='options') return parent::add($key,$value,$group,$expire);
        return $this->access(function(&$data)use($key,$value){$key=$this->site.':'.$key; if(array_key_exists($key,$data))return false; $data[$key]=$value; return true;});
    }
    public function delete($key,$group='default',$deprecated=false) {
        if ($group!=='options') return parent::delete($key,$group,$deprecated);
        return $this->access(function(&$data)use($key){unset($data[$this->site.':'.$key]); return true;});
    }
}
