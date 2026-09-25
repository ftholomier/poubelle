import type { Metadata } from 'next';
import { CrmPanel, type CrmTab } from '@/components/console/CrmPanel';
import { requirePlatformStaff } from '@/server/authz';

export const metadata: Metadata = { title: 'Suivi commercial' };

const TABS: CrmTab[] = ['act', 'onb', 'neg', 'pro', 'lost'];

type Props = { searchParams: Promise<{ crm?: string; deal?: string }> };

export default async function CrmPage({ searchParams }: Props) {
  const sp = await searchParams;
  const actor = await requirePlatformStaff();
  const tab = TABS.find((t) => t === sp.crm) ?? 'neg';
  return (
    <div className="crm-page">
      <CrmPanel tab={tab} dealId={sp.deal} base="/console/crm" canEdit={actor.isPlatformAdmin || actor.roles.some((r) => r.role === 'PLATFORM_SALES')} />
    </div>
  );
}
