#!/bin/bash
cd /home/workspace/scarlet-overlay
python3 scripts/prometheus-export.py -o prometheus-export.om 2>&1 | tee prometheus-export.log
