<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * InstaDir - v6.1 (The Ultimate Dual-Threat)
 * Supports ZIP Generation AND Bash Script Export with Smart Injections.
 * Updated: Enhanced security, UX error handling, and robust parsing.
 */



 // Put this at the VERY top of your file



// --- DATABASE CONFIGURATION ---
include "db.php";

function logVisit($pdo) {
    if (!$pdo) return;

    // Check if we've already logged this session's visit
    // if (!isset($_SESSION['has_visited'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO instadir_visits (ip_address, user_agent, referer) VALUES (?, ?, ?)");
            $stmt->execute([
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $_SERVER['HTTP_REFERER'] ?? 'direct'
            ]);
            
            // Mark the session so we don't log them again until they close the browser
            $_SESSION['has_visited'] = true;
        } catch (Exception $e) {
            error_log("Visit logging failed: " . $e->getMessage());
        }
    // }
}

// Execute the visit log immediately on every page load
logVisit($pdo);
/**
 * Helper function to log generations
 */

if ($pdo) {
    // 1. Grand Total Views (All time)
    $viewQuery = $pdo->query("SELECT COUNT(*) as total_views FROM instadir_visits");
    $totalViews = $viewQuery->fetch()['total_views'];

    // 2. Today's Views (Since 12:00 AM)
    $todayQuery = $pdo->query("SELECT COUNT(*) as today_views FROM instadir_visits WHERE DATE(created_at) = CURDATE()");
    $todayViews = $todayQuery->fetch()['today_views'];
    
    // 3. Total Project Builds
    $buildQuery = $pdo->query("SELECT COUNT(*) as total_builds FROM instadir_logs");
    $totalBuilds = $buildQuery->fetch()['total_builds'];
}

function logGeneration($pdo, $action, $framework, $lines) {
    if (!$pdo) return; // Skip if DB is down

    try {
        $stmt = $pdo->prepare("INSERT INTO instadir_logs (action_type, framework, line_count, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $action, 
            $framework, 
            count($lines), 
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Logging failed: " . $e->getMessage());
    }
}

function generateBoilerplate($path, $framework) {
    $filename = basename($path);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $className = pathinfo($filename, PATHINFO_FILENAME);

    // --- 1. CODEIGNITER 4 ---
    if ($framework === 'ci4' && $ext === 'php') {
        $dirPath = dirname($path);
        $namespaceParts = explode('/', $dirPath);
        if (isset($namespaceParts[0]) && $namespaceParts[0] === 'app') $namespaceParts[0] = 'App';
        $namespaceParts = array_filter($namespaceParts, function($p) { return $p !== '.' && !empty($p); });
        $namespace = implode('\\', $namespaceParts);

        if (strpos($path, 'Controllers/') !== false) return "<?php\n\nnamespace $namespace;\n\nuse App\Controllers\BaseController;\n\nclass $className extends BaseController\n{\n    public function index()\n    {\n        return view('welcome_message');\n    }\n}\n";
        if (strpos($path, 'Models/') !== false) {
            $tableName = strtolower(str_replace('Model', '', $className)) . 's';
            return "<?php\n\nnamespace $namespace;\n\nuse CodeIgniter\Model;\n\nclass $className extends Model\n{\n    protected \$table            = '$tableName';\n    protected \$primaryKey       = 'id';\n    protected \$allowedFields    = [];\n}\n";
        }
        return "<?php\n\nnamespace $namespace;\n\n// TODO: Implement $className\n";
    }

    // --- 2. LARAVEL ---
    if ($framework === 'laravel' && $ext === 'php') {
        $dirPath = dirname($path);
        $namespaceParts = explode('/', $dirPath);
        if (isset($namespaceParts[0]) && $namespaceParts[0] === 'app') $namespaceParts[0] = 'App';
        $namespaceParts = array_filter($namespaceParts, function($p) { return $p !== '.' && !empty($p); });
        $namespace = implode('\\', $namespaceParts);

        if (strpos($path, 'Controllers/') !== false) return "<?php\n\nnamespace $namespace;\n\nuse Illuminate\Http\Request;\nuse App\Http\Controllers\Controller;\n\nclass $className extends Controller\n{\n    public function index(Request \$request)\n    {\n        //\n    }\n}\n";
        if (strpos($path, 'Models/') !== false) return "<?php\n\nnamespace $namespace;\n\nuse Illuminate\Database\Eloquent\Factories\HasFactory;\nuse Illuminate\Database\Eloquent\Model;\n\nclass $className extends Model\n{\n    use HasFactory;\n\n    protected \$fillable = [];\n}\n";
        return "<?php\n\nnamespace $namespace;\n\n// TODO: Implement $className\n";
    }

    // --- 3. REACT (JS/TS) ---
    if ($framework === 'react' && in_array($ext, ['js', 'jsx', 'ts', 'tsx'])) {
        // Enforce strict PascalCase for React components (handles kebab-case, snake_case, and camelCase)
        $compName = preg_replace('/[^a-zA-Z0-9]/', ' ', $className); 
        $compName = str_replace(' ', '', ucwords($compName));
        $compName = ucfirst($compName); 

        return "import React from 'react';\n\nconst $compName = () => {\n  return (\n    <div className=\"$className-wrapper\">\n      <h1>$compName Component</h1>\n    </div>\n  );\n};\n\nexport default $compName;\n";
    }

    // --- 4. FLUTTER (DART) ---
    if ($framework === 'flutter' && $ext === 'dart') {
        // Dart files use snake_case, but classes must be PascalCase. 
        // Example: user_model.dart -> UserModel
        $dartClassName = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $className)));

        // 4a. main.dart Entry Point
        if ($filename === 'main.dart') {
            return "import 'package:flutter/material.dart';\n\nvoid main() {\n  runApp(const MyApp());\n}\n\nclass MyApp extends StatelessWidget {\n  const MyApp({super.key});\n\n  @override\n  Widget build(BuildContext context) {\n    return MaterialApp(\n      title: 'InstaDIR App',\n      theme: ThemeData(\n        colorScheme: ColorScheme.fromSeed(seedColor: Colors.indigo),\n        useMaterial3: true,\n      ),\n      home: const Scaffold(\n        body: Center(child: Text('Built with InstaDIR 🚀')),\n      ),\n    );\n  }\n}\n";
        }

        // 4b. Models (with JSON serialization boilerplate)
        if (strpos($path, 'models/') !== false || strpos($path, 'model/') !== false) {
            return "class $dartClassName {\n  $dartClassName();\n\n  factory $dartClassName.fromJson(Map<String, dynamic> json) {\n    return $dartClassName(\n      // TODO: Map JSON keys to properties\n    );\n  }\n\n  Map<String, dynamic> toJson() {\n    return {\n      // TODO: Map properties to JSON keys\n    };\n  }\n}\n";
        }

        // 4c. Screens / Views (StatelessWidget with Scaffold)
        if (strpos($path, 'screens/') !== false || strpos($path, 'views/') !== false || strpos($path, 'pages/') !== false) {
            return "import 'package:flutter/material.dart';\n\nclass $dartClassName extends StatelessWidget {\n  const $dartClassName({super.key});\n\n  @override\n  Widget build(BuildContext context) {\n    return Scaffold(\n      appBar: AppBar(\n        title: const Text('$dartClassName'),\n      ),\n      body: const Center(\n        child: Text('$dartClassName UI goes here'),\n      ),\n    );\n  }\n}\n";
        }

        // 4d. Controllers / Providers (State Management via ChangeNotifier)
        if (strpos($path, 'controllers/') !== false || strpos($path, 'providers/') !== false || strpos($path, 'notifiers/') !== false) {
            return "import 'package:flutter/material.dart';\n\nclass $dartClassName extends ChangeNotifier {\n  bool _isLoading = false;\n  bool get isLoading => _isLoading;\n\n  void setLoading(bool value) {\n    _isLoading = value;\n    notifyListeners();\n  }\n}\n";
        }

        // Fallback for any other Dart file
        return "class $dartClassName {\n  // TODO: Implement $dartClassName\n}\n";
    }

    // DEFAULT FALLBACKS
    if (in_array($ext, ['js', 'ts', 'css', 'scss'])) return "/* File generated by InstaDIR */\n";
    if (in_array($ext, ['html', 'xml'])) return "\n";
    if ($ext === 'php') return "<?php\n\n// File generated by InstaDIR\n";
    return ""; 
}

$errorMsg = ''; // Initialize error message variable

// --- BACKEND: Handle the Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tree_text'])) {
    
    $rawText = $_POST['tree_text'];
    $framework = $_POST['framework'] ?? 'none';
    $action = $_POST['action'] ?? 'zip';
    
    $lines = explode("\n", $rawText);
    $structure = [];
    $pathStack = [];
    
    if (count($lines) > 500) {
        $errorMsg = "Tree is too large. For performance and security reasons, trees are limited to 500 lines.";
    } else {
        foreach ($lines as $line) {
            // Normalize tabs to 4 spaces for consistent visual indent parsing
            $lineCleaned = str_replace("\t", "    ", $line);
            
            // Strip #, (, and // comments
            $lineCleaned = preg_replace('/\s*[\(#].*$/', '', $lineCleaned);
            $lineCleaned = rtrim($lineCleaned);

            if (!preg_match('/^([│├└─\s ]*)(.*)$/u', $lineCleaned, $matches)) continue; 

            $prefix = $matches[1];
            $name = trim($matches[2]);
            $name = rtrim($name, '/');
            $name = trim($name);

            if (empty($name)) continue;

            $visualIndent = mb_strlen($prefix, 'UTF-8');
            $isFile = (strpos($name, '.') !== false);

            while (!empty($pathStack) && end($pathStack)['indent'] >= $visualIndent) {
                array_pop($pathStack);
            }

            $currentNode = ['name' => $name, 'indent' => $visualIndent, 'is_file' => $isFile];
            $pathStack[] = $currentNode;
            
            $structure[] = [
                'path' => implode('/', array_column($pathStack, 'name')),
                'is_file' => $isFile
            ];
        }

        if (empty($structure)) {
            $errorMsg = "Could not parse any valid folder or file structure from the provided text.";
        }
    }

    // Proceed only if there are no errors
    if (empty($errorMsg)) {
        logGeneration($pdo, $action, $framework, $lines);
        // ==========================================
        // ACTION: BASH SCRIPT EXPORT
        // ==========================================
        if ($action === 'bash') {
            $bashScript = "#!/bin/bash\n\n";
            $bashScript .= "echo \"🚀 InstaDIR: Building your architecture...\"\n\n";

            $dirs = [];
            foreach ($structure as $item) {
                if (!$item['is_file']) {
                    $dirs[] = '"' . $item['path'] . '"';
                } else {
                    $dirs[] = '"' . dirname($item['path']) . '"'; 
                }
            }
            $dirs = array_unique($dirs);
            $dirs = array_filter($dirs, function($d) { return $d !== '""' && $d !== '"."'; });
            
            if (!empty($dirs)) {
                $bashScript .= "# Create directories\n";
                $bashScript .= "mkdir -p " . implode(" ", $dirs) . "\n\n";
            }

            $bashScript .= "# Create files and inject code\n";
            foreach ($structure as $item) {
                if ($item['is_file']) {
                    $content = generateBoilerplate($item['path'], $framework);
                    if (empty($content)) {
                        $bashScript .= "touch \"{$item['path']}\"\n";
                    } else {
                        $bashScript .= "cat << 'EOF' > \"{$item['path']}\"\n";
                        $bashScript .= $content;
                        if (substr($content, -1) !== "\n") $bashScript .= "\n";
                        $bashScript .= "EOF\n\n";
                    }
                }
            }

            $bashScript .= "echo \"✅ Architecture built successfully!\"\n";

            header('Content-Type: text/x-shellscript');
            header('Content-Disposition: attachment; filename="InstaDIR_build.sh"');
            header('Content-Length: ' . strlen($bashScript));
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $bashScript;
            exit;
        }

        // ==========================================
        // ACTION: ZIP FILE GENERATION
        // ==========================================
        
          if ($action === 'zip') {
        if (!class_exists('ZipArchive')) {
            $errorMsg = "System Error: The ZipArchive extension is not enabled on this server. Please use the Bash Script Export instead.";
        } else {
            $zip = new ZipArchive();
            $zipFileName = 'InstaDIR_project_' . time() . '.zip';
            
            // 1. Define a local temp directory within your project
            $tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'temp_exports';
            
            // 2. Create it if it doesn't exist
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }

            // 3. Create a unique path for the ZIP
            $tmpFilePath = $tmpDir . DIRECTORY_SEPARATOR . uniqid('InstaDIR_', true) . '.zip';

            if ($zip->open($tmpFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                $errorMsg = "System Error: PHP cannot write to the directory. Check folder permissions.";
            } else {
                foreach ($structure as $item) {
                    if ($item['is_file']) {
                        $fileContent = generateBoilerplate($item['path'], $framework);
                        $zip->addFromString($item['path'], $fileContent);
                    } else {
                        $zip->addEmptyDir($item['path']);
                    }
                }
                $zip->close();

                // 4. Send the file to the browser
                if (file_exists($tmpFilePath)) {
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
                    header('Content-Length: ' . filesize($tmpFilePath));
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    readfile($tmpFilePath);
                    
                    // 5. Cleanup: Delete the temp file after download
                    unlink($tmpFilePath);
                    exit;
                } else {
                    $errorMsg = "File Error: ZIP was generated but could not be read from disk.";
                }
            }
        }
    }
    }
}




?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>InstaDIR | Smart Project Architecture Builder</title>
    <meta name="title" content="InstaDIR | Smart Project Architecture Builder">
    <meta name="description" content="The ultimate folder generator for developers. Instantly convert text trees into full project architectures with context-aware code injection for flutter, Laravel, React, and more.">
    <meta name="keywords" content="folder generator, project scaffold, boilerplate code, laravel architecture, react folder structure, developer tools, AMFSTACKS">
    <meta name="author" content="AMFSTACKS">
    
    <meta name="robots" content="index, follow">
    
    <meta name="theme-color" content="#4f46e5"> 

    <link rel="canonical" href="https://instadir.dev/" />

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://instadir.dev/">
    <meta property="og:title" content="InstaDIR | Smart Architecture Builder">
    <meta property="og:description" content="Convert text trees into full project architectures instantly. Built by DEV for DEV.">
    <meta property="og:image" content="https://instadir.dev/assets/social-preview.jpg">
    <meta property="og:image:alt" content="Preview of the InstaDIR interface generating a folder structure">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://instadir.dev/">
    <meta property="twitter:title" content="InstaDIR | Smart Architecture Builder">
    <meta property="twitter:description" content="Convert text trees into full project architectures instantly. Built by DEV for DEV.">
    <meta property="twitter:image" content="https://instadir.dev/assets/social-preview.jpg">
    <meta property="twitter:image:alt" content="Preview of the InstaDIR interface generating a folder structure">

    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              // Upgraded to Plus Jakarta Sans for that premium SaaS feel
              sans: ['"Plus Jakarta Sans"', 'sans-serif'],
              mono: ['"JetBrains Mono"', 'monospace'],
            }
          }
        }
      }
    </script>
    <style>
        textarea { white-space: pre; overflow-wrap: normal; overflow-x: auto; }
        #preview_container::-webkit-scrollbar { width: 8px; height: 8px; }
        #preview_container::-webkit-scrollbar-track { background: transparent; }
        #preview_container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 font-sans">

    <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <header class="mb-2 text-center">
            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900 mb-2">InstaDIR</h1>
            <p class="text-lg text-indigo-600 font-medium">The only folder generator with context-aware code injection.</p>
        </header>

        <div class="mta-12 pat-8 mb-2 borader-t border-slate-200 text-center">
    <div class="flex flex-wrap justify-center gap-12 text-slate-400 text-xs font-bold uppercase tracking-widest">
        
        <div class="flex flex-col gap-1">
            <span class="text-indigo-500 text-md"><?php echo number_format($todayViews); ?></span>
            <span class="text-[10px]">Today's Visits</span>
        </div>

        <div class="flex flex-col gap-1 border-l border-slate-200 pl-12">
            <span class="text-slate-900 text-md"><?php echo number_format($totalViews); ?></span>
            <span class="text-[10px]">Total Visits</span>
        </div>

        <!-- <div class="flex flex-col gap-1 border-l border-slate-200 pl-12">
            <span class="text-emerald-500 text-md"><?php echo number_format($totalBuilds); ?></span>
            <span class="text-[10px]">Total Builds</span>
        </div> -->

    </div>
</div>
        <?php if (!empty($errorMsg)): ?>
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-md shadow-sm">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700 font-medium">
                        <?= htmlspecialchars($errorMsg) ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2">
                
                <div class="p-6 lg:p-8 border-b lg:border-b-0 lg:border-r border-slate-200 bg-slate-50">
                    <form method="POST" action="">
                        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Target Stack</label>
                                <select name="framework" class="w-full bg-white border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 shadow-sm">
                                    <option value="none" <?= (!isset($_POST['framework']) || $_POST['framework'] === 'none') ? 'selected' : '' ?>>Direct(as pasted)</option>
                                    <option value="flutter" <?= (isset($_POST['framework']) && $_POST['framework'] === 'flutter') ? 'selected' : '' ?>>Flutter (Dart)</option>
                                    <option value="ci4" <?= (isset($_POST['framework']) && $_POST['framework'] === 'ci4') ? 'selected' : '' ?>>CodeIgniter 4 (PHP)</option>
                                    <option value="laravel" <?= (isset($_POST['framework']) && $_POST['framework'] === 'laravel') ? 'selected' : '' ?>>Laravel (PHP)</option>
                                    <option value="react" <?= (isset($_POST['framework']) && $_POST['framework'] === 'react') ? 'selected' : '' ?>>React (JS/TS)</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-between items-end mb-2">
                            <label for="tree_text" class="block text-sm font-semibold text-slate-700">Paste your text tree here:</label>
                        </div>
                        
                        <textarea name="tree_text" id="tree_text" rows="15" class="w-full bg-slate-900 text-green-400 p-4 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm leading-relaxed shadow-inner" required><?= isset($_POST['tree_text']) ? htmlspecialchars($_POST['tree_text']) : '' ?></textarea>

                        <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-end">
                            <button type="submit" name="action" value="bash" class="w-full sm:w-auto bg-slate-800 hover:bg-slate-900 text-white font-bold py-3 px-6 rounded-lg shadow-md transition-all duration-200 flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                Export .sh Script
                            </button>
                            <button type="submit" name="action" value="zip" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg shadow-md transition-all duration-200 flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Download ZIP
                            </button>
                        </div>
                    </form>
                </div>

                <div class="p-6 lg:p-8 flex flex-col h-full">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Live Visual Preview:</h3>
                    <div class="flex-grow bg-white border border-slate-200 rounded-xl p-4 overflow-auto shadow-inner" style="min-height: 400px;" id="preview_container"></div>
                </div>

            </div>
        </div>
    </div>






<footer class="mt-20 pb-12">
    <div class="max-w-3xl mx-auto border-t border-slate-200 pt-10">
        
        <div class="text-center mb-8">
            <span class="bg-slate-900 text-slate-100 text-[10px] px-3 py-1 rounded-full font-bold uppercase tracking-[0.2em]">
                By DEV for DEV
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
            
            <div class="text-center md:text-left">
                <p class="text-slate-500 text-sm font-medium">
                    Built with 💙 by 
                    <a href="https://www.linkedin.com/in/YOUR_LINKEDIN_USERNAME" target="_blank" class="text-indigo-600 font-bold hover:underline decoration-2 underline-offset-4">
                        AMFSTACKS
                    </a>
                </p>
                <p class="text-slate-400 text-xs mt-1">Mobile and Software Developer</p>
            </div>

            <div class="flex flex-col md:items-end gap-3">
                <p class="text-slate-700 text-sm font-bold uppercase tracking-tight">Wanna contribute or suggest?</p>
                <div class="flex gap-3 justify-center">
                    <a href="https://wa.me/2348034107132?text=Hi%20AMFSTACKS,%20I%20have%20a%20suggestion%20for%20InstaDir" 
                       class="flex items-center gap-2 px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-lg transition-all shadow-md shadow-emerald-100">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        WhatsApp Me
                    </a>
                    <a href="mailto:amfstacks@gmail.com" 
                       class="flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        Send Email
                    </a>
                </div>
            </div>
        </div>

       

    </div>
</footer>

    <script>
        const textArea = document.getElementById('tree_text');
        const previewContainer = document.getElementById('preview_container');
        const folderIcon = `<svg class="w-4 h-4 text-indigo-500 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>`;
        const fileIcon = `<svg class="w-4 h-4 text-slate-400 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>`;

        function updatePreview() {
            const text = textArea.value;
            previewContainer.innerHTML = '';
            let pathStack = [], renderedItems = 0;

            text.split('\n').forEach(line => {
                // Normalize tabs to spaces in JS preview as well
                let cleaned = line.replace(/\t/g, '    ').replace(/\s*[\(#].*$/, '').trimEnd();
                const match = cleaned.match(/^([│├└─\s ]*)(.*)$/);
                if (!match) return;

                let name = match[2].trim().replace(/\/$/, '').trim();
                if (!name) return;

                const visualIndent = match[1].length;
                const isFile = name.includes('.');

                while (pathStack.length > 0 && pathStack[pathStack.length - 1].indent >= visualIndent) pathStack.pop();
                pathStack.push({ name, indent: visualIndent });
                
                const level = pathStack.length - 1;
                const itemDiv = document.createElement('div');
                itemDiv.className = `flex items-center py-1.5 hover:bg-slate-50 rounded px-2 cursor-default transition-colors duration-150 ${isFile ? 'text-slate-600' : 'text-slate-900 font-medium'}`;
                itemDiv.style.paddingLeft = `${(level * 1.5) + 0.5}rem`;
                itemDiv.innerHTML = `${isFile ? fileIcon : folderIcon} <span class="truncate tracking-tight font-mono text-sm">${name}</span>`;
                previewContainer.appendChild(itemDiv);
                renderedItems++;
            });

            if (renderedItems === 0) {
                previewContainer.innerHTML = `<div class="flex flex-col items-center justify-center h-full text-slate-400 space-y-3"><svg class="w-12 h-12 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg><p class="text-sm">Start typing or paste a tree on the left...</p></div>`;
            }
        }

        textArea.addEventListener('input', updatePreview);
        updatePreview(); // Run once on load to render prepopulated text if form fails
    </script>
</body>
</html>

