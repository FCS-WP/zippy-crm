import { useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { EmptyState } from "@/js/shared/components/EmptyState.jsx";
import { Drawer } from "../vouchers/Drawer.jsx";
import { TierForm } from "./TierForm.jsx";
import { TiersSkeleton } from "./TiersSkeleton.jsx";
import { TiersTable } from "./TiersTable.jsx";

export default function TiersPanel() {
	const [formRow, setFormRow]   = useState(null);
	const [formOpen, setFormOpen] = useState(false);

	const list = useApiQuery("/admin/tiers");

	const openCreate = () => { setFormRow(null);  setFormOpen(true); };
	const openEdit   = (row) => { setFormRow(row); setFormOpen(true); };
	const close      = () => setFormOpen(false);

	const items = list.data?.items ?? [];

	return (
		<div className="zc-space-y-5 zc-p-6">
			<header className="zc-flex zc-flex-wrap zc-items-start zc-justify-between zc-gap-3">
				<div>
					<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">Tiers</h1>
					<p className="zc-text-sm zc-text-zinc-500">
						The membership ladder. Multiplier sets points-earn rate; thresholds drive
						auto-evaluation; admin-only tiers (e.g. VIP) are sticky.
					</p>
				</div>
				<Button onClick={openCreate}>+ New tier</Button>
			</header>

			{list.isLoading ? (
				<TiersSkeleton />
			) : list.error ? (
				<div className="zc-rounded-lg zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-4 zc-text-sm zc-text-rose-800">
					{list.error.message || "Could not load tiers."}
				</div>
			) : items.length === 0 ? (
				<EmptyState
					title="No tiers configured."
					description="Add at least one tier so customers have a level to land on."
					action={<Button onClick={openCreate}>Create tier</Button>}
				/>
			) : (
				<TiersTable rows={items} onEdit={openEdit} />
			)}

			<Drawer
				open={formOpen}
				onClose={close}
				title={formRow ? `Edit tier — ${formRow.label}` : "New tier"}
				width="zc-max-w-md"
			>
				<TierForm row={formRow} onClose={close} />
			</Drawer>
		</div>
	);
}
