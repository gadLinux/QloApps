<?php
/**
 * 2026 GF Experiences
 *
 * Loads the module's classes and wires them together.
 *
 * PS 1.6 has no namespaces and no Composer autoloader, so requires are listed
 * explicitly and in dependency order. Keeping construction here rather than in
 * the module class is what lets the module class stay a facade: it asks this
 * for a collaborator instead of knowing how one is built.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFModuleServices
{
    /** Source of truth for the establishment catalogue. */
    const ESTABLISHMENTS_CSV = 'data/establishments.csv';

    /** Bookable room types, keyed to the establishments by hotel id. */
    const ROOM_TYPES_CSV = 'data/room-types.csv';

    /** @var string Absolute path to the module directory, with trailing slash. */
    private $moduleDir;

    /** @var array<string, object> Built collaborators, by key. */
    private $instances = [];

    public function __construct($moduleDir)
    {
        $this->moduleDir = rtrim($moduleDir, '/') . '/';

        self::loadClasses($this->moduleDir);
    }

    /**
     * Require every module class, in dependency order.
     *
     * Static so tests and CLI scripts can load the library without building
     * the container.
     */
    public static function loadClasses($moduleDir)
    {
        $moduleDir = rtrim($moduleDir, '/') . '/';

        $classes = [
            // Infrastructure
            'lib/Migration/GFMigrationInterface.php',
            'lib/Migration/GFSchemaHelper.php',
            'lib/Migration/GFMigrationResult.php',
            'lib/Migration/GFMigrationRepository.php',
            'lib/Migration/GFMigrationRunner.php',
            // Domain
            'lib/Repository/GFEstablishment.php',
            'lib/Repository/GFEstablishmentRepository.php',
            'lib/Repository/GFRoomType.php',
            'lib/Repository/GFHotelRepository.php',
            // Application
            'lib/Service/GFImportException.php',
            'lib/Service/GFErrorCollectingController.php',
            'lib/Service/GFImportResult.php',
            'lib/Service/GFEstablishmentCsvReader.php',
            'lib/Service/GFRoomTypeCsvReader.php',
            'lib/Service/GFImageLocator.php',
            'lib/Service/GFProductImageFactory.php',
            'lib/Service/GFEstablishmentProductFactory.php',
            'lib/Service/GFCategoryTreeBuilder.php',
            'lib/Service/GFHotelFactory.php',
            'lib/Service/GFRoomTypeFactory.php',
            'lib/Service/GFHotelProvisioner.php',
            'lib/Service/GFEstablishmentImporter.php',
            // Presentation
            'lib/Admin/GFImportPanel.php',
        ];

        foreach ($classes as $relativePath) {
            require_once $moduleDir . $relativePath;
        }

        foreach (self::discoverMigrationFiles($moduleDir) as $file) {
            require_once $file;
        }
    }

    /**
     * @return GFMigrationRunner
     */
    public function getMigrationRunner()
    {
        return $this->share('migrationRunner', function () {
            return new GFMigrationRunner(
                $this->buildMigrations(),
                new GFMigrationRepository(),
                new GFSchemaHelper()
            );
        });
    }

    /**
     * @return GFEstablishmentRepository
     */
    public function getEstablishmentRepository()
    {
        return $this->share('establishmentRepository', function () {
            return new GFEstablishmentRepository();
        });
    }

    /**
     * @return GFEstablishmentImporter
     */
    public function getEstablishmentImporter()
    {
        return $this->share('establishmentImporter', function () {
            return new GFEstablishmentImporter(
                new GFEstablishmentCsvReader(),
                new GFEstablishmentProductFactory(),
                $this->getEstablishmentRepository(),
                $this->getHotelProvisioner(),
                $this->getProductImageFactory()
            );
        });
    }

    /**
     * @return GFProductImageFactory
     */
    public function getProductImageFactory()
    {
        return $this->share('productImageFactory', function () {
            return new GFProductImageFactory(new GFImageLocator());
        });
    }

    /**
     * @return GFHotelRepository
     */
    public function getHotelRepository()
    {
        return $this->share('hotelRepository', function () {
            return new GFHotelRepository();
        });
    }

    /**
     * Provisions bookable hotels, or null when the platform cannot host them.
     *
     * A shop without hotelreservationsystem still imports establishments as
     * informational products; it just has nothing to book.
     *
     * @return GFHotelProvisioner|null
     */
    public function getHotelProvisioner()
    {
        return $this->share('hotelProvisioner', function () {
            $hotelRepository = $this->getHotelRepository();

            return new GFHotelProvisioner(
                new GFHotelFactory(new GFCategoryTreeBuilder(), $hotelRepository),
                new GFRoomTypeFactory(
                    $this->getEstablishmentRepository(),
                    $this->getProductImageFactory()
                ),
                $hotelRepository,
                $this->readRoomTypes()
            );
        });
    }

    /**
     * Absolute path to the establishments source file.
     *
     * @return string
     */
    public function getEstablishmentsCsvPath()
    {
        return $this->moduleDir . self::ESTABLISHMENTS_CSV;
    }

    /**
     * @return string
     */
    public function getRoomTypesCsvPath()
    {
        return $this->moduleDir . self::ROOM_TYPES_CSV;
    }

    /**
     * Room types grouped by hotel. A missing or unreadable file yields no room
     * types rather than failing the import — the hotels still get created.
     *
     * @return array<string, GFRoomType[]>
     */
    private function readRoomTypes()
    {
        $path = $this->getRoomTypesCsvPath();

        if (!file_exists($path)) {
            return [];
        }

        try {
            return (new GFRoomTypeCsvReader())->readGroupedByHotel($path);
        } catch (GFImportException $exception) {
            return [];
        }
    }

    /**
     * One instance of every migration class in migrations/.
     *
     * @return GFMigrationInterface[]
     */
    private function buildMigrations()
    {
        $migrations = [];

        foreach (self::discoverMigrationFiles($this->moduleDir) as $file) {
            $class = basename($file, '.php');

            if (class_exists($class) && is_subclass_of($class, 'GFMigrationInterface')) {
                $migrations[] = new $class();
            }
        }

        return $migrations;
    }

    /**
     * Migration files, sorted by name — which is also version order, because
     * the class name carries the timestamp.
     *
     * @return string[]
     */
    private static function discoverMigrationFiles($moduleDir)
    {
        $files = glob(rtrim($moduleDir, '/') . '/migrations/GFMigration*.php');

        if (!is_array($files)) {
            return [];
        }

        sort($files);

        return $files;
    }

    /**
     * @param  string   $key
     * @param  callable $factory
     * @return object
     */
    private function share($key, $factory)
    {
        if (!isset($this->instances[$key])) {
            $this->instances[$key] = $factory();
        }

        return $this->instances[$key];
    }
}
