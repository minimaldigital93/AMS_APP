<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
    <style>
        .house-body { fill: currentColor; }
        .house-roof { fill: currentColor; }
        .house-door { fill: rgba(255,255,255,0.3); }
        .house-window { fill: rgba(255,255,255,0.5); }
        .house-chimney { fill: currentColor; }
        
        /* Smoke animation */
        .smoke {
            fill: rgba(255,255,255,0.6);
            animation: smokeRise 2s ease-out infinite;
        }
        .smoke:nth-child(2) { animation-delay: 0.5s; }
        .smoke:nth-child(3) { animation-delay: 1s; }
        
        @keyframes smokeRise {
            0% {
                opacity: 0.8;
                transform: translateY(0) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(-15px) scale(1.5);
            }
        }
        
        /* Glow pulse animation */
        .window-glow {
            fill: #fbbf24;
            animation: windowPulse 2s ease-in-out infinite;
        }
        
        @keyframes windowPulse {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }
        
        /* House bounce animation */
        .house-group {
            animation: houseFloat 3s ease-in-out infinite;
            transform-origin: center bottom;
        }
        
        @keyframes houseFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
    </style>
    
    <g class="house-group">
        <!-- Chimney -->
        <rect class="house-chimney" x="65" y="25" width="10" height="20" rx="1"/>
        
        <!-- Smoke puffs -->
        <circle class="smoke" cx="70" cy="22" r="3"/>
        <circle class="smoke" cx="70" cy="18" r="2.5"/>
        <circle class="smoke" cx="70" cy="14" r="2"/>
        
        <!-- Roof -->
        <polygon class="house-roof" points="50,20 15,50 85,50"/>
        
        <!-- House body -->
        <rect class="house-body" x="22" y="50" width="56" height="40" rx="2"/>
        
        <!-- Door -->
        <rect class="house-door" x="42" y="60" width="16" height="30" rx="2"/>
        <circle cx="54" cy="77" r="1.5" fill="rgba(255,255,255,0.8)"/>
        
        <!-- Windows with glow -->
        <rect class="window-glow" x="27" y="58" width="12" height="12" rx="1"/>
        <rect class="window-glow" x="61" y="58" width="12" height="12" rx="1"/>
        
        <!-- Window frames -->
        <line x1="33" y1="58" x2="33" y2="70" stroke="currentColor" stroke-width="1"/>
        <line x1="27" y1="64" x2="39" y2="64" stroke="currentColor" stroke-width="1"/>
        <line x1="67" y1="58" x2="67" y2="70" stroke="currentColor" stroke-width="1"/>
        <line x1="61" y1="64" x2="73" y2="64" stroke="currentColor" stroke-width="1"/>
    </g>
</svg>
