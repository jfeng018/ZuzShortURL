<?php
require_once 'config.php';

$code = trim($path, '/');
if (empty($code) || in_array($code, $reserved_codes)) {
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM short_links WHERE shortcode = ?");
$stmt->execute([$code]);
$link = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$link) {
    header('Location: /');
    exit;
}

if ($link['expiration_date'] && strtotime($link['expiration_date']) < time()) {
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare("UPDATE short_links SET clicks = clicks + 1 WHERE shortcode = ?");
$stmt->execute([$code]);

if ($link['enable_intermediate_page']) {
    // Render intermediate page
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>正在跳转 - Zuz.Asia</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            border: "hsl(var(--border))",
                            input: "hsl(var(--input))",
                            ring: "hsl(var(--ring))",
                            background: "hsl(var(--background))",
                            foreground: "hsl(var(--foreground))",
                            primary: {
                                DEFAULT: "hsl(var(--primary))",
                                foreground: "hsl(var(--primary-foreground))",
                            },
                            secondary: {
                                DEFAULT: "hsl(var(--secondary))",
                                foreground: "hsl(var(--secondary-foreground))",
                            },
                            destructive: {
                                DEFAULT: "hsl(var(--destructive))",
                                foreground: "hsl(var(--destructive-foreground))",
                            },
                            muted: {
                                DEFAULT: "hsl(var(--muted))",
                                foreground: "hsl(var(--muted-foreground))",
                            },
                            accent: {
                                DEFAULT: "hsl(var(--accent))",
                                foreground: "hsl(var(--accent-foreground))",
                            },
                            popover: {
                                DEFAULT: "hsl(var(--popover))",
                                foreground: "hsl(var(--popover-foreground))",
                            },
                            card: {
                                DEFAULT: "hsl(var(--card))",
                                foreground: "hsl(var(--card-foreground))",
                            },
                        },
                        borderRadius: {
                            lg: "var(--radius)",
                            md: "calc(var(--radius) - 2px)",
                            sm: "calc(var(--radius) - 4px)",
                        },
                    },
                }
            }
        </script>
        <style>
            :root {
                --background: 0 0% 100%;
                --foreground: 222.2 84% 4.9%;
                --card: 0 0% 100%;
                --card-foreground: 222.2 84% 4.9%;
                --popover: 0 0% 100%;
                --popover-foreground: 222.2 84% 4.9%;
                --primary: 222.2 47.4% 11.2%;
                --primary-foreground: 210 40% 98%;
                --secondary: 210 40% 96%;
                --secondary-foreground: 222.2 47.4% 11.2%;
                --muted: 210 40% 96%;
                --muted-foreground: 215.4 16.3% 46.9%;
                --accent: 210 40% 96%;
                --accent-foreground: 222.2 47.4% 11.2%;
                --destructive: 0 84.2% 60.2%;
                --destructive-foreground: 210 40% 98%;
                --border: 214.3 31.8% 91.4%;
                --input: 214.3 31.8% 91.4%;
                --ring: 222.2 84% 4.9%;
                --radius: 0.5rem;
            }

            .dark {
                --background: 222.2 84% 4.9%;
                --foreground: 210 40% 98%;
                --card: 222.2 84% 4.9%;
                --card-foreground: 210 40% 98%;
                --popover: 222.2 84% 4.9%;
                --popover-foreground: 210 40% 98%;
                --primary: 210 40% 98%;
                --primary-foreground: 222.2 47.4% 11.2%;
                --secondary: 217.2 32.6% 17.5%;
                --secondary-foreground: 210 40% 98%;
                --muted: 217.2 32.6% 17.5%;
                --muted-foreground: 215 20.2% 65.1%;
                --accent: 217.2 32.6% 17.5%;
                --accent-foreground: 210 40% 98%;
                --destructive: 0 62.8% 30.6%;
                --destructive-foreground: 210 40% 98%;
                --border: 217.2 32.6% 17.5%;
                --input: 217.2 32.6% 17.5%;
                --ring: 212.7 26.8% 83.9%;
            }

            body {
                font-size: 0.875rem;
            }

            body {
                background-color: hsl(var(--background));
                background-image: 
                    repeating-linear-gradient(90deg, transparent, transparent 19px, hsl(var(--muted-foreground)/0.1) 20px, hsl(var(--muted-foreground)/0.1) 21px),
                    repeating-linear-gradient(0deg, transparent, transparent 19px, hsl(var(--muted-foreground)/0.1) 20px, hsl(var(--muted-foreground)/0.1) 21px);
                background-size: 20px 20px;
                position: relative;
            }

            body::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: inherit;
                z-index: -1;
                backdrop-filter: blur(1px);
                pointer-events: none;
            }

            .bg-card {
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                background: hsl(var(--card)/0.8);
                border: 1px solid hsl(var(--border)/0.5);
            }

            .dark .bg-card {
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                background: hsl(var(--card)/0.6);
                border: 1px solid hsl(var(--border)/0.5);
            }

            .container {
                position: relative;
                z-index: 1;
            }

            .bg-card {
                box-shadow: 0 4px 6px -1px hsl(var(--ring)/0.1), 0 2px 4px -1px hsl(var(--ring)/0.06);
            }

            .dark .bg-card {
                box-shadow: 0 4px 6px -1px hsl(var(--ring)/0.2), 0 2px 4px -1px hsl(var(--ring)/0.1);
            }

            .pricing-card {
                backdrop-filter: blur(15px);
                -webkit-backdrop-filter: blur(15px);
            }

            .hero-section {
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
            }
        </style>
        <meta http-equiv="refresh" content="5;url=<?php echo htmlspecialchars($link['longurl']); ?>">
    </head>
    <body class="bg-background text-foreground min-h-screen flex items-center justify-center">
        <div class="container mx-auto p-8">
            <div class="max-w-lg mx-auto bg-card rounded-lg border p-6 text-center">
                <h2 class="text-2xl font-bold mb-4">正在跳转...</h2>
                <p class="text-muted-foreground mb-4">您将被重定向到以下链接:</p>
                <a href="<?php echo htmlspecialchars($link['longurl']); ?>" class="text-primary font-mono break-all hover:underline"><?php echo htmlspecialchars($link['longurl']); ?></a>
                <p class="text-sm text-muted-foreground mt-4">5秒后自动跳转，或点击上面的链接立即跳转。</p>
                <div class="mt-6">
                    <a href="<?php echo htmlspecialchars($link['longurl']); ?>" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">立即跳转</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

header('Location: ' . $link['longurl']);
exit;
?>