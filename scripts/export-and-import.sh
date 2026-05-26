#!/bin/bash
cd /home/workspace/scarlet-overlay

# Wait for the resume export to finish (check for its process)
echo "Waiting for export to complete..."
while pgrep -f "prometheus-export.py" > /dev/null 2>&1; do
    sleep 5
done

echo "Export finished. Starting VictoriaMetrics import..."
python3 scripts/victoriametrics-import.py --url http://44.30.69.5:8428 -i prometheus-export.om 2>&1 | tee victoriametrics-import.log

echo "Done."
