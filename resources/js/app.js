// import './bootstrap';

import Alpine from 'alpinejs';
import {
    createIcons,
    LayoutDashboard, Users, CalendarDays, Scissors, Wallet, Receipt,
    Package, UserCog, Cake, MessageCircle, Gift, TrendingUp, Settings,
    LogOut, Menu, X, Sparkles, CircleCheck, CircleAlert, Info, Lock, ShieldCheck,
    CirclePlus, Clock, TriangleAlert,
} from 'lucide';

window.Alpine = Alpine;
Alpine.start();

const icons = {
    LayoutDashboard, Users, CalendarDays, Scissors, Wallet, Receipt,
    Package, UserCog, Cake, MessageCircle, Gift, TrendingUp, Settings,
    LogOut, Menu, X, Sparkles, CircleCheck, CircleAlert, Info, Lock, ShieldCheck,
    CirclePlus, Clock, TriangleAlert,
};

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons });

    // Chart.js n'est chargé que sur les pages qui ont un graphique.
    if (document.querySelector('canvas[data-graphique]')) {
        import('./graphiques.js').then(({ initialiserGraphiques }) => initialiserGraphiques());
    }
});
