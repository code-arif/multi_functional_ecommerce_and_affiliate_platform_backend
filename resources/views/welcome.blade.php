<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#080b12">
    <title>{{ config('app.name', 'Project') }}</title>

    <style>
        :root {
            color-scheme: dark;
            --accent: #65f0b5;
            --accent-soft: rgba(101, 240, 181, .18);
            --pointer-x: 50%;
            --pointer-y: 50%;
        }

        * { box-sizing: border-box; }

        html, body {
            width: 100%;
            min-height: 100%;
            margin: 0;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            overflow: hidden;
            padding: 24px;
            background: #080b12;
            color: #f4f7f6;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system,
                         BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .ambient {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            background:
                radial-gradient(600px circle at var(--pointer-x) var(--pointer-y),
                    rgba(101, 240, 181, .09), transparent 48%),
                radial-gradient(ellipse at 18% 20%, rgba(44, 94, 121, .18), transparent 42%),
                radial-gradient(ellipse at 82% 78%, rgba(40, 105, 83, .13), transparent 42%);
        }

        .grid {
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: .22;
            background-image:
                linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px);
            background-size: 54px 54px;
            mask-image: radial-gradient(ellipse at center, black 10%, transparent 75%);
        }

        .orb {
            position: fixed;
            width: 360px;
            aspect-ratio: 1;
            border-radius: 50%;
            filter: blur(85px);
            opacity: .13;
            pointer-events: none;
            animation: drift 13s ease-in-out infinite alternate;
        }

        .orb.one { background: #2fbb91; top: -190px; left: -140px; }
        .orb.two {
            width: 420px;
            background: #356b91;
            right: -220px;
            bottom: -230px;
            animation-delay: -5s;
        }

        .stage {
            position: relative;
            width: min(100%, 560px);
            perspective: 1000px;
            animation: enter .9s cubic-bezier(.2,.8,.2,1) both;
        }

        .card {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            padding: clamp(42px, 8vw, 68px) 28px;
            text-align: center;
            border: 1px solid rgba(255,255,255,.11);
            border-radius: 28px;
            background:
                linear-gradient(145deg, rgba(255,255,255,.075), rgba(255,255,255,.025));
            box-shadow:
                0 30px 100px rgba(0,0,0,.38),
                inset 0 1px 0 rgba(255,255,255,.08);
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
            transform-style: preserve-3d;
            transition: transform .2s ease, border-color .3s ease, box-shadow .3s ease;
        }

        .card::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            opacity: .8;
            background: radial-gradient(
                420px circle at var(--pointer-x) var(--pointer-y),
                rgba(101,240,181,.10), transparent 55%
            );
            pointer-events: none;
        }

        .card:hover {
            border-color: rgba(101,240,181,.3);
            box-shadow: 0 34px 110px rgba(0,0,0,.46), 0 0 50px rgba(101,240,181,.06);
        }

        .status-icon {
            position: relative;
            display: grid;
            place-items: center;
            width: 76px;
            height: 76px;
            margin: 0 auto 30px;
            border: 1px solid rgba(101,240,181,.35);
            border-radius: 24px;
            color: var(--accent);
            background: linear-gradient(145deg, rgba(101,240,181,.17), rgba(101,240,181,.045));
            box-shadow: 0 0 42px var(--accent-soft), inset 0 1px 0 rgba(255,255,255,.1);
            animation: float 4s ease-in-out infinite;
        }

        .status-icon::before,
        .status-icon::after {
            content: "";
            position: absolute;
            inset: -9px;
            border: 1px solid rgba(101,240,181,.2);
            border-radius: 29px;
            animation: pulse 2.8s ease-out infinite;
        }

        .status-icon::after { animation-delay: 1.4s; }

        .status-icon svg {
            width: 34px;
            height: 34px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        h1 {
            margin: 0;
            font-size: clamp(26px, 5vw, 38px);
            line-height: 1.2;
            letter-spacing: -.045em;
            font-weight: 650;
            text-wrap: balance;
        }

        .highlight {
            color: var(--accent);
            text-shadow: 0 0 28px rgba(101,240,181,.2);
        }

        .bottom-line {
            width: 54px;
            height: 3px;
            margin: 30px auto 0;
            border-radius: 99px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            box-shadow: 0 0 18px rgba(101,240,181,.55);
        }

        .particles {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: rgba(153,255,213,.7);
            box-shadow: 0 0 12px rgba(101,240,181,.7);
            animation: rise linear infinite;
        }

        @keyframes enter {
            from { opacity: 0; transform: translateY(22px) scale(.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-7px); }
        }

        @keyframes pulse {
            0% { transform: scale(.92); opacity: .65; }
            100% { transform: scale(1.28); opacity: 0; }
        }

        @keyframes drift {
            from { transform: translate3d(0, 0, 0); }
            to { transform: translate3d(55px, 45px, 0); }
        }

        @keyframes rise {
            from { transform: translateY(12vh); opacity: 0; }
            15% { opacity: .7; }
            85% { opacity: .45; }
            to { transform: translateY(-110vh); opacity: 0; }
        }

        @media (max-width: 480px) {
            body { padding: 18px; }
            .card { border-radius: 23px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="ambient"></div>
    <div class="grid"></div>
    <div class="orb one"></div>
    <div class="orb two"></div>
    <div class="particles" aria-hidden="true" id="particles"></div>

    <main class="stage">
        <section class="card" id="statusCard" aria-label="Project status">
            <div class="status-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="m5 12.5 4.5 4.5L19 7.5"></path>
                </svg>
            </div>

            <h1>Project is successfully <span class="highlight">running.</span></h1>
            <div class="bottom-line" aria-hidden="true"></div>
        </section>
    </main>

    <script>
        (() => {
            const root = document.documentElement;
            const card = document.getElementById('statusCard');
            const particles = document.getElementById('particles');
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (!reduceMotion) {
                window.addEventListener('pointermove', (event) => {
                    const x = (event.clientX / window.innerWidth) * 100;
                    const y = (event.clientY / window.innerHeight) * 100;

                    root.style.setProperty('--pointer-x', `${x}%`);
                    root.style.setProperty('--pointer-y', `${y}%`);

                    if (event.pointerType === 'mouse') {
                        const rect = card.getBoundingClientRect();
                        const rotateY = ((event.clientX - rect.left) / rect.width - .5) * 4;
                        const rotateX = ((event.clientY - rect.top) / rect.height - .5) * -4;
                        card.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
                    }
                }, { passive: true });

                card.addEventListener('pointerleave', () => {
                    card.style.transform = 'rotateX(0deg) rotateY(0deg)';
                });

                for (let i = 0; i < 18; i++) {
                    const particle = document.createElement('span');
                    particle.className = 'particle';
                    particle.style.left = `${Math.random() * 100}%`;
                    particle.style.top = `${Math.random() * 100}%`;
                    particle.style.animationDuration = `${12 + Math.random() * 18}s`;
                    particle.style.animationDelay = `${Math.random() * -25}s`;
                    particle.style.opacity = `${.2 + Math.random() * .55}`;
                    particles.appendChild(particle);
                }
            }
        })();
    </script>
</body>
</html>
