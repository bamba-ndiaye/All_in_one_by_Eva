// Graphiques du tableau de bord (Chart.js), aux couleurs du salon.
// Couleurs validées pour le daltonisme : bordeaux #8f2e4d (recettes) et doré #b8862f (dépenses).
import {
    Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale,
    Filler, Tooltip,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip);

const COULEURS = { recettes: '#8f2e4d', depenses: '#b8862f' };
const TEXTE = '#57534e';      // stone-600 : le texte ne prend jamais la couleur d'une série
const GRILLE = '#efe8de';     // un cran au-dessus du fond, discret
const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Le canvas n'affiche pas toujours l'espace fine (U+202F) d'Intl : on met une espace insécable classique.
const nombre = (n, options) => new Intl.NumberFormat('fr-FR', options).format(n).replace(/\u202f/g, '\u00a0');
const fcfa = (n) => nombre(n) + '\u00a0FCFA';
const compact = (n) => (Math.abs(n) >= 1000 ? nombre(n / 1000, { maximumFractionDigits: 1 }) + ' k' : String(n));
const dateFr = (iso) => iso.split('-').reverse().join('/');

/** Dégradé vertical bordeaux → transparent sous la courbe des recettes. */
function degrade(ctx) {
    const { chart } = ctx;
    const zone = chart.chartArea;
    if (!zone) return 'transparent';
    const g = chart.ctx.createLinearGradient(0, zone.top, 0, zone.bottom);
    g.addColorStop(0, COULEURS.recettes + '38');   // ~22 %
    g.addColorStop(1, COULEURS.recettes + '00');
    return g;
}

/** Le mois en cours n'est pas fini : son segment est en pointillés. */
const segmentEnCours = (points) => ({
    borderDash: (ctx) => (points()[ctx.p1DataIndex]?.en_cours ? [4, 4] : undefined),
});

/** Seul le dernier point (aujourd'hui / mois en cours) est marqué. */
const dernierPoint = (rayon) => (ctx) => (ctx.dataIndex === ctx.dataset.data.length - 1 ? rayon : 0);

function courbeRecettesDepenses(canvas) {
    const series = {
        '7j': JSON.parse(canvas.dataset.serie || '[]').slice(-7),
        '30j': JSON.parse(canvas.dataset.serie || '[]'),
        '12m': JSON.parse(canvas.dataset.serieMois || '[]'),
    };
    let points = series[canvas.dataset.periode] ?? series['30j'];

    const chart = new Chart(canvas, {
        type: 'line',
        data: { labels: [], datasets: [
            {
                label: 'Recettes',
                data: [],
                borderColor: COULEURS.recettes,
                backgroundColor: degrade,
                pointBackgroundColor: COULEURS.recettes,
                fill: 'origin',
                order: 1,
                segment: segmentEnCours(() => points),
            },
            {
                label: 'Dépenses',
                data: [],
                borderColor: COULEURS.depenses,
                backgroundColor: COULEURS.depenses,
                pointBackgroundColor: COULEURS.depenses,
                fill: false,
                order: 2,
                segment: segmentEnCours(() => points),
            },
        ] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduit ? false : { duration: 350, easing: 'easeOutCubic' },
            interaction: { mode: 'index', intersect: false },
            elements: {
                line: { borderWidth: 2, borderCapStyle: 'round', borderJoinStyle: 'round', tension: 0.3, cubicInterpolationMode: 'monotone' },
                point: {
                    radius: dernierPoint(4),
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverRadius: 6,
                    hoverBorderWidth: 2,
                    hitRadius: 14,
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: GRILLE },
                    ticks: { color: TEXTE, maxRotation: 0, autoSkip: true, maxTicksLimit: 8, font: { size: 11 } },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: GRILLE, lineWidth: 1 },
                    border: { display: false },
                    ticks: { color: TEXTE, maxTicksLimit: 5, font: { size: 11 }, callback: (v) => compact(v) },
                },
            },
            plugins: {
                // La légende (avec les totaux) est en HTML au-dessus du graphique.
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#292524',
                    bodyColor: TEXTE,
                    footerColor: '#292524',
                    borderColor: GRILLE,
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 4,
                    usePointStyle: true,
                    titleFont: { weight: '600' },
                    footerFont: { weight: '600' },
                    callbacks: {
                        title: (items) => {
                            const p = points[items[0].dataIndex];
                            return (p.titre ?? dateFr(p.date)) + (p.en_cours ? ' (en cours)' : '');
                        },
                        label: (ctx) => ` ${ctx.dataset.label} : ${fcfa(ctx.parsed.y)}`,
                        footer: (items) => {
                            const p = points[items[0].dataIndex];
                            const solde = p.encaisse - p.depenses;
                            return `Solde : ${solde > 0 ? '+' : ''}${fcfa(solde)}`;
                        },
                    },
                },
            },
        },
    });

    const afficher = (periode) => {
        points = series[periode] ?? series['30j'];
        chart.data.labels = points.map((p) => p.libelle);
        chart.data.datasets[0].data = points.map((p) => p.encaisse);
        chart.data.datasets[1].data = points.map((p) => p.depenses);
        chart.update();
    };

    afficher(canvas.dataset.periode);
    canvas.addEventListener('periode', (e) => afficher(e.detail));

    return chart;
}

export function initialiserGraphiques() {
    document.querySelectorAll('canvas[data-graphique="recettes-depenses"]').forEach((canvas) => {
        try {
            courbeRecettesDepenses(canvas);
        } catch (e) {
            console.error('Graphique impossible à afficher', e);
        }
    });
}
