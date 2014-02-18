<?php
/**
 * Class for rewriting cookies
 **/
class Amcsi_HttpProxy_CookieRewriter
{
    private $cookieHeaderValue;
    private $sourcePath;
    private $targetPath;
    private $targetHost;

    public function setSourcePath($value)
    {
        $this->sourcePath = $value;
        return $this;
    }

    public function setTargetPath($value)
    {
        $this->targetPath = $value;
        return $this;
    }

    /**
     * Any cookies filtered should have their paths use this as a
     * minimum base.
     * 
     * @access private
     * @return void
     */
    private function getUseSourcePath()
    {
        return ($this->sourcePath && '/' === $this->sourcePath[0]) ?
            $this->sourcePath :
            '/'
        ;
    }

    public function setTargetHost($value)
    {
        $this->targetHost = $value;
        return $this;
    }

    public function getFilteredSetCookie($setCookie)
    {
        $oldParts = explode('; ', $setCookie);
        $newParts = $oldParts;
        foreach ($newParts as &$part) {
            if (0 === strpos($part, 'path=')) {
                $path = substr($part, 5);
                $useSourcePath = $this->getUseSourcePath();
                if (
                    $this->targetPath &&
                    0 === strpos($path, $this->targetPath)
                ) {
                    // path starts with targetPath, so we can do path filtering
                    $newPath = substr_replace(
                        $path,
                        $useSourcePath,
                        0,
                        strlen($this->targetPath)
                    );
                    $part = "path=$newPath";
                } else {
                    // otherwise fall back to / which means any path
                    $part = "path=$useSourcePath";
                }
            } elseif (0 === strpos($part, 'domain')) {
                // must rewrite the domain for it to work.
                if ($this->targetHost) {
                    // domain only works if there is at least 1 dot in it.
                    $domain = false !== strpos(substr($this->targetHost, 1), '.') ?
                        $this->targetHost :
                        '';
                    $part = "domain=$domain";
                }
            }
        }
        $ret = join('; ', $newParts);
        return $ret;
    }
}
