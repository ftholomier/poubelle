import { calendarIcs, type IcsEvent } from '@/lib/ics';
import { fromParisLocal, parisDate } from '@/lib/format';

/**
 * Agenda de démonstration d'un office de tourisme (mode démo uniquement) : source iCal « externe » synchronisée
 * dans l'agenda du Val de Loue, avec des dates toujours à venir.
 */
const ITEMS: { in: number; at: string; hours: number; title: string; place: string; text: string }[] = [
  {
    in: 3,
    at: '10:00',
    hours: 2,
    title: 'Visite guidée : Ornans, la petite Venise comtoise',
    place: 'Office de tourisme, Ornans',
    text: 'Maisons sur pilotis, ponts et ateliers : la vieille ville racontée par un guide. Sur inscription.',
  },
  {
    in: 6,
    at: '20:30',
    hours: 2,
    title: 'Concert de l’harmonie à l’église de Lods',
    place: 'Église Saint-Théodule, Lods',
    text: 'Répertoire de musiques de films. Participation libre.',
  },
  {
    in: 10,
    at: '09:00',
    hours: 4,
    title: 'Randonnée accompagnée aux sources de la Loue',
    place: 'Parking de la source, Ouhans',
    text: 'Boucle de 9 km, dénivelé modéré. Prévoir de bonnes chaussures.',
  },
  {
    in: 14,
    at: '18:00',
    hours: 4,
    title: 'Marché nocturne des producteurs',
    place: 'Place de la mairie, Quingey',
    text: 'Producteurs du territoire, food-trucks et animation musicale.',
  },
  {
    in: 21,
    at: '15:00',
    hours: 1.5,
    title: 'Conférence : Courbet et la vallée de la Loue',
    place: 'Musée Courbet, Ornans',
    text: 'Les paysages peints par Courbet, du Puits-Noir à la source.',
  },
  {
    in: 30,
    at: '14:00',
    hours: 3,
    title: 'Atelier vannerie en famille',
    place: 'Maison du Patrimoine, Vuillafans',
    text: 'Initiation à la vannerie d’osier, dès 7 ans. Matériel fourni.',
  },
];

export function demoExternalAgendaIcs(now = new Date()): string {
  const events: IcsEvent[] = ITEMS.map((it, i) => {
    const day = parisDate(new Date(now.getTime() + it.in * 86_400_000));
    const startsAt = fromParisLocal(day, it.at);
    return {
      uid: `ot-loue-lison-${i + 1}@exemple-office-tourisme.fr`,
      title: it.title,
      description: it.text,
      location: it.place,
      url: 'https://www.exemple-office-tourisme.fr/agenda',
      startsAt,
      endsAt: new Date(startsAt.getTime() + it.hours * 3_600_000),
    };
  });
  return calendarIcs('Office de tourisme Loue-Lison', events, { now });
}
