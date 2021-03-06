<?php
namespace PHPJava\Core;

use PHPJava\Core\JVM\Parameters\GlobalOptions;
use PHPJava\Core\JVM\Parameters\Runtime;
use PHPJava\Exceptions\UndefinedEntrypointException;
use PHPJava\Imitation\java\io\FileNotFoundException;
use PHPJava\Imitation\java\lang\_Object;
use PHPJava\Imitation\java\lang\ClassNotFoundException;
use PHPJava\Utilities\ClassResolver;
use PHPJava\Utilities\DebugTool;
use PHPJava\Utilities\FileTypeResolver;

class JavaArchive
{
    const MANIFEST_FILE_NAME = 'META-INF/MANIFEST.MF';
    const DEFAULT_ENTRYPOINT_NAME = 'main';
    const IGNORE_FILES = [
        'META-INF/main.kotlin_module',
    ];

    private $manifestData = [];
    private $jarFile;
    private $expandedHArchive;
    private $files = [];
    private $classes = [];
    private $options = [];
    private $debugTool;
    private $startTime = 0.0;

    /**
     * @param string $jarFile
     * @param array $options
     * @throws FileNotFoundException
     * @throws \PHPJava\Exceptions\ReadEntryException
     * @throws \PHPJava\Exceptions\ValidatorException
     * @throws \PHPJava\Imitation\java\lang\ClassNotFoundException
     */
    public function __construct(string $jarFile, array $options = [])
    {
        $this->startTime = microtime(true);
        $this->jarFile = $jarFile;
        $archive = new \ZipArchive();
        $archive->open($jarFile);
        $this->expandedHArchive = $archive;
        $this->options = $options;
        $this->debugTool = new DebugTool(
            basename($jarFile),
            $this->options
        );

        $this->debugTool->getLogger()->info('Start jar emulation');

        $this->manifestData['main-class'] = $options['entrypoint'] ?? Runtime::ENTRYPOINT;

        // Add resolving path
        ClassResolver::add(
            [
                [ClassResolver::RESOURCE_TYPE_FILE, dirname($jarFile)],
                [ClassResolver::RESOURCE_TYPE_FILE, getcwd()],
                [ClassResolver::RESOURCE_TYPE_JAR, $this],
            ]
        );

        $this->debugTool->getLogger()->debug('Extracting jar files: ' . $this->expandedHArchive->numFiles);
        for ($i = 0; $i < $this->expandedHArchive->numFiles; $i++) {
            $name = $archive->getNameIndex($i);
            if ($name[strlen($name) - 1] === '/') {
                continue;
            }
            $fileName = preg_replace('/\.class$/', '', $name);
            $this->files[$fileName] = $archive->getFromIndex($i);
        }

        if (!isset($this->files[static::MANIFEST_FILE_NAME])) {
            throw new FileNotFoundException('Failed to load Manifest.mf');
        }

        foreach (explode("\n", $this->files[static::MANIFEST_FILE_NAME]) as $attribute) {
            $attribute = str_replace(["\r", "\n"], '', $attribute);
            if (empty($attribute)) {
                continue;
            }
            [$name, $value] = explode(':', $attribute);
            $this->manifestData[strtolower($name)] = trim($value);
        }

        $this->files = array_filter(
            $this->files,
            function ($fileName) {
                return $fileName !== static::MANIFEST_FILE_NAME;
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($this->files as $className => $code) {
            if (in_array($className, static::IGNORE_FILES)) {
                continue;
            }
            $classPath = str_replace('/', '.', $className);
            if (!($this->options['preload'] ?? GlobalOptions::get('preload') ?? Runtime::PRELOAD)) {
                $this->classes[$classPath] = new JavaClassDeferredLoader(
                    JavaClassInlineReader::class,
                    [$className, $code],
                    $this->options
                );
                continue;
            }

            $this->classes[$classPath] = new JavaClass(
                new JavaClassInlineReader(
                    $className,
                    $code
                ),
                $this->options
            );
        }

        $currentDirectory = getcwd();
        foreach ($this->getClassPaths() as $classPath) {
            $resolvePath = $classPath[0] === '/' ? $classPath : ($currentDirectory . '/' . $classPath);
            $realpath = realpath($resolvePath);
            if ($realpath === false) {
                throw new FileNotFoundException($classPath . ' does not exist.');
            }

            $value = $realpath;

            switch ($fileType = FileTypeResolver::resolve($resolvePath)) {
                case ClassResolver::RESOLVED_TYPE_CLASS:
                    $value = new JavaClassFileReader($value);
                    break;
                case ClassResolver::RESOURCE_TYPE_JAR:
                    $value = new JavaArchive($value, $this->options);
                    break;
                case ClassResolver::RESOURCE_TYPE_FILE:
                    break;
            }
            ClassResolver::add($fileType, $value);
        }

        $this->debugTool->getLogger()->info('End of jar');
    }

    public function __destruct()
    {
        $this->debugTool->getLogger()->info(
            'Spent time: ' . (microtime(true) - $this->startTime) . ' sec.'
        );
    }

    /**
     * @param mixed ...$arguments
     * @return mixed
     * @throws ClassNotFoundException
     * @throws UndefinedEntrypointException
     */
    public function execute(...$arguments)
    {
        $this->debugTool->getLogger()->info('Call to entrypoint: ' . $this->getEntryPointName());
        if ($this->getEntryPointName() === null) {
            throw new UndefinedEntrypointException('No entrypoint.');
        }
        return $this
            ->getClassByName($this->getEntryPointName())
            ->getInvoker()
            ->getStatic()
            ->getMethods()
            ->call(
                static::DEFAULT_ENTRYPOINT_NAME,
                ...$arguments
            );
    }

    public function __debugInfo()
    {
        return [
            'version' => $this->getVersion(),
            'createdBy' => $this->getCreatedBy(),
            'entryPointName' => $this->getEntryPointName(),
            'file' => $this->jarFile,
            'classes' => $this->getClasses(),
            'classPaths' => $this->getClassPaths(),
        ];
    }

    public function getVersion(): ?string
    {
        return $this->manifestData['manifest-version'] ?? null;
    }

    public function getCreatedBy(): ?string
    {
        return $this->manifestData['created-by'] ?? null;
    }

    public function getEntryPointName(): ?string
    {
        return $this->manifestData['main-class'] ?? null;
    }

    public function getClassPaths(): array
    {
        $classPaths = [];
        foreach (explode(' ', $this->manifestData['class-path'] ?? '') as $path) {
            if (empty($path)) {
                continue;
            }
            $classPaths[] = $path;
        }
        return $classPaths;
    }

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function getClassByName(string $name): JavaClassInterface
    {
        $name = str_replace('/', '.', $name);
        if (!isset($this->classes[$name])) {
            throw new ClassNotFoundException($name . ' does not found on ' . $this->jarFile . '.');
        }
        return $this->classes[$name];
    }
}
