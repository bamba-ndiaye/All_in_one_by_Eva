// Graphiques du tableau de bord (Chart.js), aux couleurs du salon.
// Couleurs validées pour le daltonisme : bordeaux #8f2e4d (recettes) et doré #b8862f (dépenses).
import {
    Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale,
    Filler, Tooltip, Legend,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend);

const COULEURS = { recettes: '#8f2e4d', depenses: '#b8862f' };
const TEXTE = '#57534e';      // stone-600 : le texte ne prend jamais la couleur d'une série
const GRILLE = '#efe8de';     // un cran au-dessus du fond, discret

const fcfa = (n) => new Intl.NumberFormat('fr-FR').format(n) + ' FCFA';
const compact = (n) => (n >= 1000 ? new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(n / 1000) + ' k' : String(n));

function courbeRecettesDepenses(canvas, serie) {
    const transparent = (hex) => hex + '1a'; // ~10 % d'opacité

    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: serie.map((j) => j.libelle),
            datasets: [
                {
                    label: 'Recettes',
                    data: serie.map((j) => j.encaisse),
                    borderColor: COULEURS.recettes,
                    backgroundColor: transparent(COULEURS.recettes),
                    fill: 'origin',
                },
                {
                    label: 'Dépenses',
                    data: serie.map((j) => j.depenses),
                    borderColor: COULEURS.depenses,
                    backgroundColor: COULEURS.depenses,
                    fill: false,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'index', intersect: false },
            elements: {
                line: { borderWidth: 2, borderCapStyle: 'round', borderJoinStyle: 'round', tension: 0.25 },
                point: { radius: 0, hoverRadius: 5, hoverBorderWidth: 2, hoverBorderColor: '#ffffff', hitRadius: 12 },
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
                legend: {
                    position: 'top',
                    align: 'start',
                    labels: { color: TEXTE, usePointStyle: true, pointStyle: 'line', boxWidth: 20, font: { size: 12 } },
                },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#292524',
                    bodyColor: TEXTE,
                    borderColor: GRILLE,
                    borderWidth: 1,
                    padding: 10,
                    usePointStyle: true,
                    callbacks: {
                        title: (items) => serie[items[0].dataIndex].date.split('-').reverse().join('/'),
                        label: (ctx) => ` ${ctx.dataset.label} : ${fcfa(ctx.parsed.y)}`,
                    },
                },
            },
        },
    });
}

export function initialiserGraphiques() {
    document.querySelectorAll('canvas[data-graphique="recettes-depenses"]').forEach((canvas) => {
        try {
            courbeRecettesDepenses(canvas, JSON.parse(canvas.dataset.serie || '[]'));
        } catch (e) {
            console.error('Graphique impossible à afficher', e);
        }
    });
}
