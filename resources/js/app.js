// import './bootstrap';

import Alpine from 'alpinejs';
import {
    createIcons,
    LayoutDashboard, Users, CalendarDays, Scissors, Wallet, Receipt,
    Package, UserCog, Cake, MessageCircle, Gift, TrendingUp, Settings,
    LogOut, Menu, X, Sparkles, CircleCheck, CircleAlert, Info, Lock, ShieldCheck,
} from 'lucide';

window.Alpine = Alpine;
Alpine.start();

const icons = {
    LayoutDashboard, Users, CalendarDays, Scissors, Wallet, Receipt,
    Package, UserCog, Cake, MessageCircle, Gift, TrendingUp, Settings,
    LogOut, Menu, X, Sparkles, CircleCheck, CircleAlert, Info, Lock, ShieldCheck,
};

document.addEventListener('DOMContentLoaded', () => createIcons({ icons }));
