import { useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { Drawer } from "../vouchers/Drawer.jsx";
import { TierForm } from "./TierForm.jsx";
import { TierMembersDrawer } from "./TierMembersDrawer.jsx";
import { TiersTable } from "./TiersTable.jsx";

export default function TiersPanel() {
	const [formRow, setFormRow]       = useState(null);
	const [formOpen, setFormOpen]     = useState(false);
	const [membersTier, setMembersTier] = useState(null);

	const list = useApiQuery("/admin/tiers");

	const openCreate = () => { setFormRow(null);  setFormOpen(true); };
	const openEdit   = (row) => { setFormRow(row); setFormOpen(true); };
	const close      = () => setFormOpen(false);

	const items = list.data?.items ?? [];

	return (
		<div className="zc-space-y-4 zc-p-6">
			<header className="zc-flex zc-flex-wrap zc-items-start zc-justify-between zc-gap-3">
				<div>
					<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">Tiers</h1>
					<p className="zc-text-sm zc-text-zinc-500">
						The membership ladder. Multiplier sets points-earn rate; thresholds drive
						auto-evaluation; admin-only tiers (e.g. VIP) are sticky. Click a tier to see
						its members.
					</p>
				</div>
				<Button onClick={openCreate}>+ New tier</Button>
			</header>

			<TiersTable
				rows={items}
				loading={list.isLoading}
				error={list.error?.message}
				onEdit={openEdit}
				onOpenMembers={setMembersTier}
			/>

			<Drawer
				open={formOpen}
				onClose={close}
				title={formRow ? `Edit tier — ${formRow.label}` : "New tier"}
				width="zc-max-w-md"
			>
				<TierForm row={formRow} onClose={close} />
			</Drawer>

			<Drawer
				open={Boolean(membersTier)}
				onClose={() => setMembersTier(null)}
				title={membersTier ? `Members on ${membersTier.label}` : "Members"}
				width="zc-max-w-2xl"
			>
				{membersTier ? <TierMembersDrawer tier={membersTier} /> : null}
			</Drawer>
		</div>
	);
}
